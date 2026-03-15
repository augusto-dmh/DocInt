<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = $this->resolveSharedTenant($request);
        $user = $request->user();
        $isSuperAdmin = $user instanceof User && $user->hasSuperAdminRole();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn () => $user,
                'roles' => fn () => $this->sharedRoles($user, $tenant),
                'permissions' => fn () => $this->sharedPermissions($user, $tenant),
                'isSuperAdmin' => fn () => $isSuperAdmin,
            ],
            'tenant' => $tenant?->only('id', 'name', 'slug', 'logo_url'),
            'tenantContext' => fn () => $this->sharedTenantContext($request, $tenant, $isSuperAdmin),
            'realtime' => fn () => $this->sharedRealtimeConfig(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    protected function resolveSharedTenant(Request $request): ?Tenant
    {
        if (tenant() instanceof Tenant) {
            return tenant();
        }

        $user = $request->user();

        if ($user instanceof User && is_string($user->tenant_id) && $user->tenant_id !== '') {
            return Tenant::query()->find($user->tenant_id);
        }

        if (! $user instanceof User || ! method_exists($user, 'hasSuperAdminRole') || ! $user->hasSuperAdminRole()) {
            return null;
        }

        $sessionKey = config('tenancy.tenant_context.session_key');
        $resolvedSessionKey = is_string($sessionKey) && $sessionKey !== ''
            ? $sessionKey
            : 'active_tenant_id';
        $tenantId = $request->session()->get($resolvedSessionKey);

        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        return Tenant::query()->find($tenantId);
    }

    /**
     * @return list<string>
     */
    protected function sharedRoles(?User $user, ?Tenant $tenant): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return $this->withPermissionTeamContext($tenant?->id, fn (): array => $user->getRoleNames()->values()->all());
    }

    /**
     * @return list<string>
     */
    protected function sharedPermissions(?User $user, ?Tenant $tenant): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return $this->withPermissionTeamContext($tenant?->id, fn (): array => $user->getAllPermissions()->pluck('name')->values()->all());
    }

    /**
     * @return array{canSelect: bool, activeTenantId: string|null, activeTenant: array{id: string, name: string, slug: string}|null}
     */
    protected function sharedTenantContext(Request $request, ?Tenant $tenant, bool $isSuperAdmin): array
    {
        return [
            'canSelect' => $isSuperAdmin,
            'activeTenantId' => $tenant?->id,
            'activeTenant' => $tenant?->only('id', 'name', 'slug'),
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     broadcaster: string,
     *     appKey: string|null,
     *     host: string|null,
     *     port: int|null,
     *     scheme: string|null,
     *     channels: array{
     *         tenantDocumentsPattern: string,
     *         documentPattern: string
     *     }
     * }
     */
    protected function sharedRealtimeConfig(): array
    {
        $clientConfig = config('reverb.client', []);
        $broadcaster = config('broadcasting.default', 'null');
        $appKey = is_array($clientConfig) && is_string($clientConfig['app_key'] ?? null) && $clientConfig['app_key'] !== ''
            ? $clientConfig['app_key']
            : null;
        $host = is_array($clientConfig) && is_string($clientConfig['host'] ?? null) && $clientConfig['host'] !== ''
            ? $clientConfig['host']
            : null;
        $scheme = is_array($clientConfig) && is_string($clientConfig['scheme'] ?? null) && $clientConfig['scheme'] !== ''
            ? $clientConfig['scheme']
            : null;
        $port = is_array($clientConfig) && is_numeric($clientConfig['port'] ?? null)
            ? (int) $clientConfig['port']
            : null;

        return [
            'enabled' => $broadcaster === 'reverb' && $appKey !== null,
            'broadcaster' => is_string($broadcaster) ? $broadcaster : 'null',
            'appKey' => $appKey,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'channels' => [
                'tenantDocumentsPattern' => 'tenants.{tenantId}.documents',
                'documentPattern' => 'documents.{documentId}',
            ],
        ];
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function withPermissionTeamContext(?string $tenantId, callable $callback): mixed
    {
        if (! function_exists('getPermissionsTeamId') || ! function_exists('setPermissionsTeamId')) {
            return $callback();
        }

        $originalTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($originalTeamId);
        }
    }
}

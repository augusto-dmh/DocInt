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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'tenant' => $tenant?->only('id', 'name', 'slug', 'logo_url'),
            'tenantContext' => [
                'canSelect' => false,
                'activeTenantId' => $tenant?->id,
            ],
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
            return $user->tenant;
        }

        return null;
    }
}

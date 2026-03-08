<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenantContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function __construct(
        protected DomainTenantResolver $domainTenantResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $tenant = $this->resolveTenant($request);

        if (! $tenant instanceof Tenant) {
            $this->denyAccess();
        }

        if (! $this->isUserAllowedForTenant($request->user(), $tenant, $request)) {
            $this->denyAccess();
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        return $this->resolveTenantFromDomain($request)
            ?? $this->resolveTenantFromAuthenticatedUser($request->user())
            ?? $this->resolveTenantFromSuperAdminSession($request, $request->user())
            ?? $this->resolveTenantFromHeaderFallback($request);
    }

    protected function resolveTenantFromDomain(Request $request): ?Tenant
    {
        if (! $this->canResolveByDomain()) {
            return null;
        }

        foreach ($this->domainCandidates($request->getHost()) as $domainCandidate) {
            try {
                $tenant = $this->domainTenantResolver->resolve($domainCandidate);
            } catch (TenantCouldNotBeIdentifiedOnDomainException) {
                continue;
            } catch (QueryException) {
                return null;
            }

            $resolvedTenant = $this->normalizeTenant($tenant);

            if ($resolvedTenant instanceof Tenant) {
                return $resolvedTenant;
            }
        }

        return null;
    }

    protected function resolveTenantFromAuthenticatedUser(mixed $user): ?Tenant
    {
        if (! $user instanceof User || ! is_string($user->tenant_id) || $user->tenant_id === '') {
            return null;
        }

        return $this->findTenant($user->tenant_id);
    }

    protected function resolveTenantFromSuperAdminSession(Request $request, mixed $user): ?Tenant
    {
        if (! $user instanceof User || ! $this->hasSuperAdminRole($user)) {
            return null;
        }

        $tenantId = $request->session()->get($this->sessionKey());

        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        return $this->findTenant($tenantId);
    }

    protected function resolveTenantFromHeaderFallback(Request $request): ?Tenant
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        $headerValue = $request->header($this->headerName());

        if (! is_string($headerValue) || $headerValue === '') {
            return null;
        }

        return $this->findTenant($headerValue);
    }

    protected function isUserAllowedForTenant(mixed $user, Tenant $tenant, Request $request): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($this->hasSuperAdminRole($user)) {
            $selectedTenantId = $request->session()->get($this->sessionKey());

            return ! is_string($selectedTenantId)
                || $selectedTenantId === ''
                || $selectedTenantId === $tenant->getTenantKey();
        }

        if (is_string($user->tenant_id) && $user->tenant_id !== '') {
            return $user->tenant_id === $tenant->getTenantKey();
        }

        return $this->headerFallbackMatchesTenant($request, $tenant);
    }

    protected function findTenant(string $tenantId): ?Tenant
    {
        return Tenant::query()->find($tenantId);
    }

    /**
     * @return list<string>
     */
    protected function domainCandidates(string $host): array
    {
        $candidates = [$host];
        $centralDomains = config('tenancy.central_domains', []);

        if (is_array($centralDomains)) {
            foreach ($centralDomains as $centralDomain) {
                if (! is_string($centralDomain) || $centralDomain === '') {
                    continue;
                }

                $suffix = '.'.$centralDomain;

                if (! Str::endsWith($host, $suffix)) {
                    continue;
                }

                $subdomain = Str::beforeLast($host, $suffix);

                if ($subdomain !== '') {
                    $candidates[] = $subdomain;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    protected function canResolveByDomain(): bool
    {
        $domainModelClass = config('tenancy.domain_model');

        if (! is_string($domainModelClass) || ! is_subclass_of($domainModelClass, Model::class)) {
            return false;
        }

        try {
            return Schema::hasTable((new $domainModelClass)->getTable());
        } catch (QueryException) {
            return false;
        }
    }

    protected function headerName(): string
    {
        $headerName = config('tenancy.tenant_context.header');

        return is_string($headerName) && $headerName !== ''
            ? $headerName
            : 'X-Tenant-ID';
    }

    protected function sessionKey(): string
    {
        $sessionKey = config('tenancy.tenant_context.session_key');

        return is_string($sessionKey) && $sessionKey !== ''
            ? $sessionKey
            : 'active_tenant_id';
    }

    protected function normalizeTenant(TenantContract $tenant): ?Tenant
    {
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        $tenantId = $tenant->getTenantKey();

        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        return $this->findTenant($tenantId);
    }

    protected function hasSuperAdminRole(User $user): bool
    {
        return method_exists($user, 'hasSuperAdminRole') && $user->hasSuperAdminRole();
    }

    protected function headerFallbackMatchesTenant(Request $request, Tenant $tenant): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return false;
        }

        $headerValue = $request->header($this->headerName());

        return is_string($headerValue) && $headerValue !== '' && $headerValue === $tenant->getTenantKey();
    }

    protected function denyAccess(): never
    {
        abort(Response::HTTP_FORBIDDEN, 'Tenant context is required for this request.');
    }
}

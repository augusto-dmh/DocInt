<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TenantContextUpdateRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantContextController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->ensureSuperAdmin($request->user());

        return Inertia::render('settings/TenantContext', [
            'tenants' => Tenant::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'activeTenantId' => $this->activeTenantId($request),
        ]);
    }

    public function update(TenantContextUpdateRequest $request): RedirectResponse
    {
        $request->session()->put(
            $this->sessionKey(),
            $request->validated('tenant_id'),
        );

        return to_route('tenant-context.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request->user());
        $request->session()->forget($this->sessionKey());

        return to_route('tenant-context.edit');
    }

    protected function ensureSuperAdmin(?User $user): void
    {
        if (! $user instanceof User || ! $user->hasSuperAdminRole()) {
            abort(403);
        }
    }

    protected function activeTenantId(Request $request): ?string
    {
        $tenantId = $request->session()->get($this->sessionKey());

        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        if (Tenant::query()->whereKey($tenantId)->exists()) {
            return $tenantId;
        }

        $request->session()->forget($this->sessionKey());

        return null;
    }

    protected function sessionKey(): string
    {
        $sessionKey = config('tenancy.tenant_context.session_key');

        return is_string($sessionKey) && $sessionKey !== ''
            ? $sessionKey
            : 'active_tenant_id';
    }
}

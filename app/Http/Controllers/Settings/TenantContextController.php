<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TenantContextUpdateRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantContextController extends Controller
{
    public function edit(Request $request): Response
    {
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
            config('tenancy.tenant_context.session_key', 'active_tenant_id'),
            $request->validated('tenant_id'),
        );

        return to_route('tenant-context.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(config('tenancy.tenant_context.session_key', 'active_tenant_id'));

        return to_route('tenant-context.edit');
    }

    protected function activeTenantId(Request $request): ?string
    {
        $sessionKey = config('tenancy.tenant_context.session_key', 'active_tenant_id');
        $tenantId = $request->session()->get($sessionKey);

        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        if (Tenant::query()->whereKey($tenantId)->exists()) {
            return $tenantId;
        }

        $request->session()->forget($sessionKey);

        return null;
    }
}

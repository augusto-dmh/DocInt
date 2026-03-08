<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['web', 'auth', 'tenant'])
        ->get('/__testing/tenant-probe', function (): JsonResponse {
            $tenant = tenant();

            return response()->json([
                'tenant_id' => $tenant?->getTenantKey(),
            ]);
        })
        ->name('testing.tenant.probe');
});

afterEach(function (): void {
    tenancy()->end();
});

test('tenant resolves from the authenticated user without a header', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)
        ->get('/__testing/tenant-probe')
        ->assertOk()
        ->assertJson([
            'tenant_id' => $tenant->id,
        ]);
});

test('domain mapping resolves the tenant when a matching domain exists', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $tenant->domains()->create([
        'domain' => 'tenant-a.localhost',
    ]);

    $this->actingAs($user)
        ->get('http://tenant-a.localhost/__testing/tenant-probe')
        ->assertOk()
        ->assertJson([
            'tenant_id' => $tenant->id,
        ]);
});

test('header fallback works in testing when the user has no assigned tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get('/__testing/tenant-probe')
        ->assertOk()
        ->assertJson([
            'tenant_id' => $tenant->id,
        ]);
});

test('invalid tenant context returns forbidden', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => 'missing-tenant'])
        ->get('/__testing/tenant-probe')
        ->assertForbidden();
});

test('cross-tenant domain access is denied', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenantA)->create();

    $tenantB->domains()->create([
        'domain' => 'tenant-b.localhost',
    ]);

    $this->actingAs($user)
        ->get('http://tenant-b.localhost/__testing/tenant-probe')
        ->assertForbidden();
});

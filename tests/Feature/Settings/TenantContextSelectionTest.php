<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    tenancy()->end();
    setPermissionsTeamId(null);
});

function tenantContextSessionKey(): string
{
    $sessionKey = config('tenancy.tenant_context.session_key');

    return is_string($sessionKey) && $sessionKey !== ''
        ? $sessionKey
        : 'active_tenant_id';
}

test('super-admin can view tenant context settings page', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $superAdmin = createSettingsSuperAdminWithTeamContext($tenantA);

    $this->actingAs($superAdmin)
        ->get(route('tenant-context.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TenantContext')
            ->has('tenants', 2)
            ->where('activeTenantId', null)
            ->where('tenantContext.canSelect', true)
        );
});

test('non super-admin users cannot access tenant context settings page', function (): void {
    $tenant = Tenant::factory()->create();
    $tenantAdmin = createSettingsTenantAdmin($tenant);

    $this->actingAs($tenantAdmin)
        ->get(route('tenant-context.edit'))
        ->assertForbidden();
});

test('super-admin can select tenant context via settings route', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $superAdmin = createSettingsSuperAdminWithTeamContext($tenantA);

    $this->actingAs($superAdmin)
        ->put(route('tenant-context.update'), [
            'tenant_id' => $tenantB->id,
        ])
        ->assertRedirect(route('tenant-context.edit'))
        ->assertSessionHasNoErrors();

    expect(session(tenantContextSessionKey()))->toBe($tenantB->id);
});

test('selected tenant context allows super-admin to access tenant-scoped routes', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSettingsSuperAdminWithTeamContext($tenant);

    $this->actingAs($superAdmin)
        ->put(route('tenant-context.update'), [
            'tenant_id' => $tenant->id,
        ])
        ->assertRedirect(route('tenant-context.edit'));

    $this->get(route('clients.index'))
        ->assertSuccessful();
});

test('super-admin without tenant context cannot access tenant-scoped routes', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSettingsSuperAdminWithTeamContext($tenant);

    $this->actingAs($superAdmin)
        ->get(route('clients.index'))
        ->assertForbidden();
});

test('super-admin can clear tenant context and loses tenant-scoped access', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSettingsSuperAdminWithTeamContext($tenant);

    $this->actingAs($superAdmin)
        ->withSession([tenantContextSessionKey() => $tenant->id])
        ->delete(route('tenant-context.destroy'))
        ->assertRedirect(route('tenant-context.edit'));

    $this->get(route('clients.index'))
        ->assertForbidden();
});

test('tenant context selection validates tenant id', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSettingsSuperAdminWithTeamContext($tenant);

    $this->actingAs($superAdmin)
        ->from(route('tenant-context.edit'))
        ->put(route('tenant-context.update'), [
            'tenant_id' => 'missing-tenant',
        ])
        ->assertSessionHasErrors('tenant_id')
        ->assertRedirect(route('tenant-context.edit'));
});

test('shared inertia data exposes selected tenant context for super-admin', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSettingsSuperAdminWithTeamContext($tenant);

    $this->actingAs($superAdmin)
        ->withSession([tenantContextSessionKey() => $tenant->id])
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenantContext.canSelect', true)
            ->where('tenantContext.activeTenantId', $tenant->id)
            ->where('tenantContext.activeTenant.id', $tenant->id)
            ->where('tenantContext.activeTenant.name', $tenant->name)
            ->where('tenantContext.activeTenant.slug', $tenant->slug)
        );
});

function createSettingsSuperAdminWithTeamContext(Tenant $tenant): User
{
    $superAdmin = User::factory()->create();

    setPermissionsTeamId($tenant->id);
    $superAdmin->assignRole('super-admin');
    setPermissionsTeamId(null);

    return $superAdmin;
}

function createSettingsTenantAdmin(Tenant $tenant): User
{
    $tenantAdmin = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $tenantAdmin->assignRole('tenant-admin');
    setPermissionsTeamId(null);

    return $tenantAdmin;
}

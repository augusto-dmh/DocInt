<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

test('seeder creates the expected roles and permissions', function (): void {
    expect(Role::query()->pluck('name')->all())->toEqualCanonicalizing([
        'super-admin',
        'tenant-admin',
        'partner',
        'associate',
        'client',
    ]);

    expect(Permission::query()->pluck('name')->all())->toEqualCanonicalizing([
        'view clients',
        'create clients',
        'edit clients',
        'delete clients',
        'view matters',
        'create matters',
        'edit matters',
        'delete matters',
        'view documents',
        'create documents',
        'edit documents',
        'delete documents',
        'review documents',
        'approve documents',
        'manage users',
        'manage tenant',
    ]);
});

test('roles are scoped to the current tenant team context', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenantA)->create();

    setPermissionsTeamId($tenantA->id);
    $user->assignRole('partner');

    setPermissionsTeamId($tenantA->id);
    $user->unsetRelation('roles');
    expect($user->hasRole('partner'))->toBeTrue();

    setPermissionsTeamId($tenantB->id);
    $user->unsetRelation('roles');
    expect($user->hasRole('partner'))->toBeFalse();
});

test('super admins bypass authorization checks', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->create();
    $partner = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $superAdmin->assignRole('super-admin');
    $partner->assignRole('partner');
    setPermissionsTeamId(null);

    Gate::define('view-admin-surface', fn (): bool => false);

    expect(Gate::forUser($superAdmin)->allows('view-admin-surface'))->toBeTrue();
    expect(Gate::forUser($partner)->allows('view-admin-surface'))->toBeFalse();
});

test('shared auth payload exposes tenant scoped roles and permissions', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');
    setPermissionsTeamId(null);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isSuperAdmin', false)
            ->where('auth.roles', ['tenant-admin'])
            ->has('auth.permissions', 16)
            ->where('tenantContext.canSelect', false)
        );
});

test('shared auth payload marks super admins as tenant selectable', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->create();

    setPermissionsTeamId($tenant->id);
    $superAdmin->assignRole('super-admin');
    setPermissionsTeamId(null);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isSuperAdmin', true)
            ->where('auth.roles', ['super-admin'])
            ->where('tenantContext.canSelect', true)
            ->where('tenantContext.activeTenantId', $tenant->id)
        );
});

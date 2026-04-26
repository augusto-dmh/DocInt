<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function makeSuperAdmin(Tenant $assignmentTenant): User
{
    $user = User::factory()->create();

    setPermissionsTeamId($assignmentTenant->id);
    $user->assignRole('super-admin');
    setPermissionsTeamId(null);

    return $user;
}

test('superadmin auth.permissions payload contains every database permission', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = makeSuperAdmin($tenant);
    $allPermissionNames = Permission::query()->pluck('name')->all();

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isSuperAdmin', true)
            ->where('auth.permissions', $allPermissionNames)
        );
});

test('superadmin auth.permissions remains complete after switching active tenant session', function (): void {
    $assignmentTenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $superAdmin = makeSuperAdmin($assignmentTenant);
    $allPermissionNames = Permission::query()->pluck('name')->all();

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $otherTenant->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', $allPermissionNames));
});

test('tenant-admin auth.permissions count matches the seeder declared set', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');
    setPermissionsTeamId(null);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('auth.permissions', 16));
});

test('superadmin can view a document after switching active tenant to its owner', function (): void {
    $assignmentTenant = Tenant::factory()->create();
    $superAdmin = makeSuperAdmin($assignmentTenant);

    $documentTenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $documentTenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $documentTenant->id, 'client_id' => $client->id]);
    $document = Document::factory()->create(['tenant_id' => $documentTenant->id, 'matter_id' => $matter->id]);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $documentTenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful();
});

test('regular partner cannot view a document belonging to a foreign tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $partner = User::factory()->forTenant($tenant)->create();
    setPermissionsTeamId($tenant->id);
    $partner->assignRole('partner');
    setPermissionsTeamId(null);

    $foreignTenant = Tenant::factory()->create();
    $foreignClient = Client::factory()->create(['tenant_id' => $foreignTenant->id]);
    $foreignMatter = Matter::factory()->create(['tenant_id' => $foreignTenant->id, 'client_id' => $foreignClient->id]);
    $foreignDocument = Document::factory()->create(['tenant_id' => $foreignTenant->id, 'matter_id' => $foreignMatter->id]);

    $this->actingAs($partner)
        ->get(route('documents.show', $foreignDocument))
        ->assertNotFound();
});

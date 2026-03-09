<?php

use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function createClientAuthContext(string $role): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return [$tenant, $user, $client];
}

describe('tenant-admin', function (): void {
    test('can view clients list', function (): void {
        [$tenant, $user] = createClientAuthContext('tenant-admin');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.index'))
            ->assertSuccessful();
    });

    test('can create a client', function (): void {
        [$tenant, $user] = createClientAuthContext('tenant-admin');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('clients.store'), ['name' => 'New Client'])
            ->assertRedirect(route('clients.index'));
    });

    test('can edit a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('tenant-admin');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('clients.update', $client), ['name' => 'Updated'])
            ->assertRedirect(route('clients.show', $client));
    });

    test('can delete a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('tenant-admin');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));
    });
});

describe('partner', function (): void {
    test('can view clients list', function (): void {
        [$tenant, $user] = createClientAuthContext('partner');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.index'))
            ->assertSuccessful();
    });

    test('can create a client', function (): void {
        [$tenant, $user] = createClientAuthContext('partner');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('clients.store'), ['name' => 'New Client'])
            ->assertRedirect(route('clients.index'));
    });

    test('can edit a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('partner');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('clients.update', $client), ['name' => 'Updated'])
            ->assertRedirect(route('clients.show', $client));
    });

    test('cannot delete a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('partner');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();
    });
});

describe('associate', function (): void {
    test('can view clients list', function (): void {
        [$tenant, $user] = createClientAuthContext('associate');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.index'))
            ->assertSuccessful();
    });

    test('can create a client', function (): void {
        [$tenant, $user] = createClientAuthContext('associate');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('clients.store'), ['name' => 'New Client'])
            ->assertRedirect(route('clients.index'));
    });

    test('can edit a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('associate');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('clients.update', $client), ['name' => 'Updated'])
            ->assertRedirect(route('clients.show', $client));
    });

    test('cannot delete a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('associate');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();
    });
});

describe('client role', function (): void {
    test('can view clients list', function (): void {
        [$tenant, $user] = createClientAuthContext('client');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.index'))
            ->assertSuccessful();
    });

    test('can view a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('client');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.show', $client))
            ->assertSuccessful();
    });

    test('cannot create a client', function (): void {
        [$tenant, $user] = createClientAuthContext('client');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('clients.store'), ['name' => 'New Client'])
            ->assertForbidden();
    });

    test('cannot edit a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('client');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('clients.update', $client), ['name' => 'Updated'])
            ->assertForbidden();
    });

    test('cannot delete a client', function (): void {
        [$tenant, $user, $client] = createClientAuthContext('client');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();
    });
});

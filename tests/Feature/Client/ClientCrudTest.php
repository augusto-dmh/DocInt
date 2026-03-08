<?php

use App\Models\Client;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    tenancy()->end();
});

function createClientCrudContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    return [$tenant, $user];
}

test('client index page can be rendered', function (): void {
    [$tenant, $user] = createClientCrudContext();
    Client::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Index')
            ->has('clients.data', 2)
            ->where('clients.data.0.tenant_id', $tenant->id)
        );
});

test('client create page can be rendered', function (): void {
    [, $user] = createClientCrudContext();

    $this->actingAs($user)
        ->get(route('clients.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('clients/Create'));
});

test('client can be stored', function (): void {
    [, $user] = createClientCrudContext();

    $this->actingAs($user)
        ->post(route('clients.store'), [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '555-1234',
            'company' => 'Test Corp',
            'notes' => 'Some notes',
        ])
        ->assertRedirect(route('clients.index'));

    expect(Client::query()->where('name', 'Test Client')->exists())->toBeTrue();
});

test('client store validates required and tenant unique fields', function (): void {
    [$tenant, $user] = createClientCrudContext();
    Client::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'client@example.com',
    ]);

    $this->actingAs($user)
        ->from(route('clients.create'))
        ->post(route('clients.store'), [
            'email' => 'client@example.com',
        ])
        ->assertRedirect(route('clients.create'))
        ->assertSessionHasErrors(['name', 'email']);
});

test('client show page can be rendered with related matters', function (): void {
    [$tenant, $user] = createClientCrudContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($user)
        ->get(route('clients.show', $client))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Show')
            ->where('client.id', $client->id)
            ->where('client.matters.0.id', $matter->id)
        );
});

test('client edit page can be rendered', function (): void {
    [$tenant, $user] = createClientCrudContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get(route('clients.edit', $client))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Edit')
            ->where('client.id', $client->id)
        );
});

test('client can be updated', function (): void {
    [$tenant, $user] = createClientCrudContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->put(route('clients.update', $client), [
            'name' => 'Updated Client',
            'email' => 'updated@example.com',
            'phone' => '555-9999',
            'company' => 'Updated Corp',
            'notes' => 'Updated notes',
        ])
        ->assertRedirect(route('clients.show', $client));

    expect($client->fresh())
        ->name->toBe('Updated Client')
        ->email->toBe('updated@example.com');
});

test('client can be destroyed', function (): void {
    [$tenant, $user] = createClientCrudContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->delete(route('clients.destroy', $client))
        ->assertRedirect(route('clients.index'));

    expect(Client::query()->find($client->id))->toBeNull();
});

test('cross tenant client access is denied by tenant scoped binding', function (): void {
    [, $user] = createClientCrudContext();
    $otherTenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($user)
        ->get(route('clients.show', $client))
        ->assertNotFound();
});

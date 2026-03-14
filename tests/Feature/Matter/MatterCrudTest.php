<?php

use App\Models\Client;
use App\Models\Document;
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
    setPermissionsTeamId(null);
    tenancy()->end();
});

function createMatterCrudContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    return [$tenant, $user, $client];
}

test('matter index page can be rendered', function (): void {
    [$tenant, $user, $client] = createMatterCrudContext();
    Matter::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($user)
        ->get(route('matters.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Index')
            ->has('matters.data', 2)
            ->where('matters.data.0.client.id', $client->id)
        );
});

test('matter create page can be rendered with available clients', function (): void {
    [, $user, $client] = createMatterCrudContext();

    $this->actingAs($user)
        ->get(route('matters.create'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Create')
            ->has('clients', 1)
            ->where('clients.0.id', $client->id)
        );
});

test('matter can be stored', function (): void {
    [, $user, $client] = createMatterCrudContext();

    $this->actingAs($user)
        ->post(route('matters.store'), [
            'client_id' => $client->id,
            'title' => 'Test Matter',
            'description' => 'A test matter',
            'reference_number' => 'MAT-100',
            'status' => 'open',
        ])
        ->assertRedirect(route('matters.index'));

    expect(Matter::query()->where('title', 'Test Matter')->exists())->toBeTrue();
});

test('matter store validates required, enum, and tenant unique fields', function (): void {
    [$tenant, $user, $client] = createMatterCrudContext();
    Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'reference_number' => 'MAT-100',
    ]);

    $this->actingAs($user)
        ->from(route('matters.create'))
        ->post(route('matters.store'), [
            'client_id' => $client->id,
            'reference_number' => 'MAT-100',
            'status' => 'invalid',
        ])
        ->assertRedirect(route('matters.create'))
        ->assertSessionHasErrors(['title', 'reference_number', 'status']);
});

test('matter show page can be rendered with related document data', function (): void {
    [$tenant, $user, $client] = createMatterCrudContext();
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('matters.show', $matter))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Show')
            ->where('matter.id', $matter->id)
            ->where('matter.documents.0.id', $document->id)
        );
});

test('matter edit page can be rendered', function (): void {
    [$tenant, $user, $client] = createMatterCrudContext();
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($user)
        ->get(route('matters.edit', $matter))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Edit')
            ->where('matter.id', $matter->id)
            ->has('clients', 1)
        );
});

test('matter can be updated', function (): void {
    [$tenant, $user, $client] = createMatterCrudContext();
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($user)
        ->put(route('matters.update', $matter), [
            'client_id' => $client->id,
            'title' => 'Updated Matter',
            'description' => 'Updated description',
            'reference_number' => 'MAT-200',
            'status' => 'closed',
        ])
        ->assertRedirect(route('matters.show', $matter));

    expect($matter->fresh())
        ->title->toBe('Updated Matter')
        ->status->value->toBe('closed');
});

test('matter can be destroyed', function (): void {
    [$tenant, $user, $client] = createMatterCrudContext();
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($user)
        ->delete(route('matters.destroy', $matter))
        ->assertRedirect(route('matters.index'));

    expect(Matter::query()->find($matter->id))->toBeNull();
});

test('matter store sets tenant_id to the current tenant', function (): void {
    [$tenant, $user, $client] = createMatterCrudContext();

    $this->actingAs($user)
        ->post(route('matters.store'), [
            'client_id' => $client->id,
            'title' => 'Tenant Matter',
            'status' => 'open',
        ]);

    $matter = Matter::query()->where('title', 'Tenant Matter')->first();

    expect($matter)->not->toBeNull()
        ->and($matter->tenant_id)->toBe($tenant->id);
});

test('matter update rejects duplicate tenant-scoped reference number', function (): void {
    [$tenant, $user, $client] = createMatterCrudContext();
    Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'reference_number' => 'MAT-TAKEN',
    ]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $this->actingAs($user)
        ->from(route('matters.edit', $matter))
        ->put(route('matters.update', $matter), [
            'client_id' => $client->id,
            'title' => $matter->title,
            'reference_number' => 'MAT-TAKEN',
            'status' => 'open',
        ])
        ->assertRedirect(route('matters.edit', $matter))
        ->assertSessionHasErrors(['reference_number']);
});

test('unauthenticated users cannot access matter routes', function (): void {
    $this->get(route('matters.index'))->assertRedirect(route('login'));
    $this->post(route('matters.store'))->assertRedirect(route('login'));
});

test('cross tenant matter access is denied by tenant scoped binding', function (): void {
    [, $user] = createMatterCrudContext();
    $otherTenant = Tenant::factory()->create();
    $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => $otherClient->id,
    ]);

    $this->actingAs($user)
        ->get(route('matters.show', $matter))
        ->assertNotFound();
});

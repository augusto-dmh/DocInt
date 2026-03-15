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

function createDocumentFlowUiContext(bool $withMatter = true): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = $withMatter
        ? Matter::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ])
        : null;

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    return [$tenant, $user, $client, $matter];
}

test('document index supports empty flow state payload', function (): void {
    [$tenant, $user] = createDocumentFlowUiContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->has('documents.data', 0)
            ->where('documentExperience.themeKey', 'phase2-ledger-v1')
        );
});

test('matter show returns latest documents with uploader metadata for flow rendering', function (): void {
    [$tenant, $user, $client, $matter] = createDocumentFlowUiContext();

    $olderDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'created_at' => now()->subDay(),
    ]);

    $newerDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.show', $matter))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Show')
            ->where('matter.client.id', $client->id)
            ->has('matter.documents', 2)
            ->where('matter.documents.0.id', $newerDocument->id)
            ->where('matter.documents.0.uploader.id', $user->id)
            ->where('matter.documents.1.id', $olderDocument->id)
            ->where('documentExperience.themeKey', 'phase2-ledger-v1')
        );
});

test('matter show supports empty document state payload', function (): void {
    [$tenant, $user, , $matter] = createDocumentFlowUiContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.show', $matter))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Show')
            ->has('matter.documents', 0)
            ->where('documentExperience.themeKey', 'phase2-ledger-v1')
        );
});

test('client show returns matter document counts for navigation context', function (): void {
    [$tenant, $user, $client] = createDocumentFlowUiContext(false);

    $matterWithDocuments = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'created_at' => now()->subDay(),
    ]);

    $matterWithoutDocuments = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'created_at' => now(),
    ]);

    Document::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matterWithDocuments->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('clients.show', $client))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Show')
            ->where('documentExperience.themeKey', 'phase2-ledger-v1')
            ->has('client.matters', 2)
            ->where('client.matters.0.id', $matterWithoutDocuments->id)
            ->where('client.matters.0.documents_count', 0)
            ->where('client.matters.1.id', $matterWithDocuments->id)
            ->where('client.matters.1.documents_count', 2)
        );
});

test('client index includes document experience payload and matter counts', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    $olderClient = Client::factory()->create([
        'tenant_id' => $tenant->id,
        'created_at' => now()->subDay(),
    ]);
    $newerClient = Client::factory()->create([
        'tenant_id' => $tenant->id,
        'created_at' => now(),
    ]);

    Matter::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $olderClient->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('clients.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Index')
            ->where('documentExperience.themeKey', 'phase2-ledger-v1')
            ->has('clients.data', 2)
            ->where('clients.data.0.id', $newerClient->id)
            ->where('clients.data.0.matters_count', 0)
            ->where('clients.data.1.id', $olderClient->id)
            ->where('clients.data.1.matters_count', 2)
        );
});

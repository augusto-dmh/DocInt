<?php

use App\Models\AuditLog;
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
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function createDocumentFinalPolishContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    return [$tenant, $user];
}

test('document show includes recent activity timeline payload', function (): void {
    [$tenant, $user] = createDocumentFinalPolishContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    AuditLog::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'auditable_type' => Document::class,
        'auditable_id' => $document->id,
        'action' => 'uploaded',
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);
    AuditLog::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'auditable_type' => Document::class,
        'auditable_id' => $document->id,
        'action' => 'downloaded',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('document.id', $document->id)
            ->has('recentActivity', 3)
            ->where('recentActivity.0.action', 'viewed')
            ->where('recentActivity.0.user.id', $user->id)
            ->where('recentActivity.1.action', 'downloaded')
            ->where('recentActivity.2.action', 'uploaded')
            ->where('documentExperience.themeKey', 'phase2-ledger-v1')
        );
});

test('client index includes document experience payload and matter counts', function (): void {
    [$tenant, $user] = createDocumentFinalPolishContext();

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

test('matter index includes document experience payload and document counts', function (): void {
    [$tenant, $user] = createDocumentFinalPolishContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $olderMatter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'created_at' => now()->subDay(),
    ]);
    $newerMatter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
        'created_at' => now(),
    ]);

    Document::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $olderMatter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Index')
            ->where('documentExperience.themeKey', 'phase2-ledger-v1')
            ->has('matters.data', 2)
            ->where('matters.data.0.id', $newerMatter->id)
            ->where('matters.data.0.documents_count', 0)
            ->where('matters.data.1.id', $olderMatter->id)
            ->where('matters.data.1.documents_count', 2)
        );
});

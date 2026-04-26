<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentAnnotation;
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

function createTenantUserWithRole(string $role = 'tenant-admin'): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return [$tenant, $user];
}

function makeForeignTenantDocument(): Document
{
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    return Document::factory()->create(['tenant_id' => $tenant->id, 'matter_id' => $matter->id]);
}

function makeForeignTenantDocumentWithComment(): array
{
    $document = makeForeignTenantDocument();
    $comment = \App\Models\DocumentComment::factory()->create([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
    ]);

    return [$document, $comment];
}

test('clients index only returns records for the authenticated users tenant', function (): void {
    [$tenant, $user] = createTenantUserWithRole();
    $otherTenant = Tenant::factory()->create();

    Client::factory()->count(2)->create(['tenant_id' => $tenant->id]);
    Client::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Index')
            ->has('clients.data', 2)
            ->where('clients.data.0.tenant_id', $tenant->id)
            ->where('clients.data.1.tenant_id', $tenant->id)
        );
});

test('matters index only returns records for the authenticated users tenant', function (): void {
    [$tenant, $user] = createTenantUserWithRole();
    $tenantClient = Client::factory()->create(['tenant_id' => $tenant->id]);

    $otherTenant = Tenant::factory()->create();
    $otherTenantClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);

    Matter::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'client_id' => $tenantClient->id,
    ]);

    Matter::factory()->count(3)->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => $otherTenantClient->id,
    ]);

    $this->actingAs($user)
        ->get(route('matters.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Index')
            ->has('matters.data', 2)
            ->where('matters.data.0.tenant_id', $tenant->id)
            ->where('matters.data.1.tenant_id', $tenant->id)
        );
});

test('creating a client in one tenant does not leak into another tenants index', function (): void {
    [$tenantA, $userA] = createTenantUserWithRole();
    [, $userB] = createTenantUserWithRole();

    $this->actingAs($userA)
        ->post(route('clients.store'), [
            'name' => 'Tenant A Client',
            'email' => 'tenant-a-client@example.com',
        ])
        ->assertRedirect(route('clients.index'));

    $this->actingAs($userB)
        ->get(route('clients.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Index')
            ->has('clients.data', 0)
        );

    tenancy()->initialize($tenantA);
    expect(Client::query()->count())->toBe(1);
});

test('creating a matter in one tenant does not leak into another tenants index', function (): void {
    [$tenantA, $userA] = createTenantUserWithRole();
    $tenantAClient = Client::factory()->create(['tenant_id' => $tenantA->id]);

    [$tenantB, $userB] = createTenantUserWithRole();
    Client::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($userA)
        ->post(route('matters.store'), [
            'client_id' => $tenantAClient->id,
            'title' => 'Tenant A Matter',
            'reference_number' => 'TENANT-A-001',
            'status' => 'open',
        ])
        ->assertRedirect(route('matters.index'));

    $this->actingAs($userB)
        ->get(route('matters.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Index')
            ->has('matters.data', 0)
        );

    tenancy()->initialize($tenantA);
    expect(Matter::query()->count())->toBe(1);

    tenancy()->initialize($tenantB);
    expect(Matter::query()->count())->toBe(0);
});

test('documents index only returns records for the authenticated users tenant', function (): void {
    [$tenant, $user] = createTenantUserWithRole();
    $tenantClient = Client::factory()->create(['tenant_id' => $tenant->id]);
    $tenantMatter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $tenantClient->id,
    ]);

    $otherTenant = Tenant::factory()->create();
    $otherTenantClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherTenantMatter = Matter::factory()->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => $otherTenantClient->id,
    ]);

    Document::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $tenantMatter->id,
    ]);

    Document::factory()->count(3)->create([
        'tenant_id' => $otherTenant->id,
        'matter_id' => $otherTenantMatter->id,
    ]);

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->has('documents.data', 2)
            ->where('documents.data.0.tenant_id', $tenant->id)
            ->where('documents.data.1.tenant_id', $tenant->id)
        );
});

test('document routes deny cross tenant access', function (): void {
    [$tenantA, $userA] = createTenantUserWithRole();
    Client::factory()->create(['tenant_id' => $tenantA->id]);

    $tenantB = Tenant::factory()->create();
    $tenantBClient = Client::factory()->create(['tenant_id' => $tenantB->id]);
    $tenantBMatter = Matter::factory()->create([
        'tenant_id' => $tenantB->id,
        'client_id' => $tenantBClient->id,
    ]);
    $tenantBDocument = Document::factory()->create([
        'tenant_id' => $tenantB->id,
        'matter_id' => $tenantBMatter->id,
    ]);

    $this->actingAs($userA)
        ->get(route('matters.documents.create', $tenantBMatter))
        ->assertNotFound();

    $this->actingAs($userA)
        ->get(route('documents.show', $tenantBDocument))
        ->assertNotFound();

    $this->actingAs($userA)
        ->get(route('documents.download', $tenantBDocument))
        ->assertNotFound();
});

test('document annotation routes deny cross tenant access', function (): void {
    [$tenantA, $userA] = createTenantUserWithRole();
    Client::factory()->create(['tenant_id' => $tenantA->id]);

    $tenantB = Tenant::factory()->create();
    $tenantBClient = Client::factory()->create(['tenant_id' => $tenantB->id]);
    $tenantBMatter = Matter::factory()->create([
        'tenant_id' => $tenantB->id,
        'client_id' => $tenantBClient->id,
    ]);
    $tenantBDocument = Document::factory()->create([
        'tenant_id' => $tenantB->id,
        'matter_id' => $tenantBMatter->id,
        'mime_type' => 'application/pdf',
    ]);
    $tenantBAnnotation = DocumentAnnotation::factory()->create([
        'tenant_id' => $tenantB->id,
        'document_id' => $tenantBDocument->id,
    ]);

    $this->actingAs($userA)
        ->post(route('documents.annotations.store', $tenantBDocument), [
            'type' => 'highlight',
            'page_number' => 1,
            'coordinates' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.1,
            ],
        ])
        ->assertNotFound();

    $this->actingAs($userA)
        ->delete(route('documents.annotations.destroy', [$tenantBDocument, $tenantBAnnotation]))
        ->assertNotFound();
});

test('comment store route denies cross tenant access', function (): void {
    [, $userA] = createTenantUserWithRole();
    $foreignDocument = makeForeignTenantDocument();

    $this->actingAs($userA)
        ->postJson(route('documents.comments.store', $foreignDocument), [
            'body' => 'Should never persist.',
        ])
        ->assertNotFound();
});

test('comment update route denies cross tenant access', function (): void {
    [, $userA] = createTenantUserWithRole();
    [$foreignDocument, $foreignComment] = makeForeignTenantDocumentWithComment();

    $this->actingAs($userA)
        ->patchJson(route('documents.comments.update', [$foreignDocument, $foreignComment]), [
            'body' => 'Should never apply.',
        ])
        ->assertNotFound();
});

test('comment destroy route denies cross tenant access', function (): void {
    [, $userA] = createTenantUserWithRole();
    [$foreignDocument, $foreignComment] = makeForeignTenantDocumentWithComment();

    $this->actingAs($userA)
        ->deleteJson(route('documents.comments.destroy', [$foreignDocument, $foreignComment]))
        ->assertNotFound();
});

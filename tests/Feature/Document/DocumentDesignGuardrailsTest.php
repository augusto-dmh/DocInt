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

function expectedDocumentExperienceGuardrails(): array
{
    return [
        'themeKey' => 'phase2-ledger-v1',
        'wrappers' => [
            'root' => 'documents-experience',
            'hero' => 'doc-hero',
            'surface' => 'doc-surface',
        ],
        'typography' => [
            'title' => 'doc-title',
            'subtle' => 'doc-subtle',
            'seal' => 'doc-seal',
        ],
        'motion' => [
            'reveal' => 'doc-fade-up',
            'delay1' => 'doc-delay-1',
            'delay2' => 'doc-delay-2',
        ],
    ];
}

function createDocumentGuardrailsContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
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

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    return [$tenant, $user, $matter, $document];
}

test('document index includes document experience guardrails', function (): void {
    [$tenant, $user] = createDocumentGuardrailsContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->where('documentExperience', expectedDocumentExperienceGuardrails())
        );
});

test('document create includes document experience guardrails', function (): void {
    [$tenant, $user, $matter] = createDocumentGuardrailsContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.documents.create', $matter))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Create')
            ->where('documentExperience', expectedDocumentExperienceGuardrails())
        );
});

test('document show includes document experience guardrails', function (): void {
    [$tenant, $user, , $document] = createDocumentGuardrailsContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('documentExperience', expectedDocumentExperienceGuardrails())
        );
});

test('document edit includes document experience guardrails', function (): void {
    [$tenant, $user, , $document] = createDocumentGuardrailsContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.edit', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Edit')
            ->where('documentExperience', expectedDocumentExperienceGuardrails())
        );
});

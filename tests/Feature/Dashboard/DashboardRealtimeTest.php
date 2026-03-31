<?php

use App\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
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

function createDashboardMatter(Tenant $tenant): Matter
{
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    return Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
}

test('dashboard shares tenant scoped stats and recent documents', function (): void {
    $tenant = Tenant::factory()->create();
    $user = createTenantAdmin($tenant);
    $matter = createDashboardMatter($tenant);

    $otherTenant = Tenant::factory()->create();
    $otherMatter = createDashboardMatter($otherTenant);

    $latestDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'title' => 'Latest upload',
        'status' => DocumentStatus::Uploaded,
        'updated_at' => now(),
    ]);

    $pendingReviewDocument = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'title' => 'Needs review',
        'updated_at' => now()->subMinute(),
    ]);

    $reviewedDocument = Document::factory()->reviewed()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'title' => 'Reviewed packet',
        'updated_at' => now()->subMinutes(2),
    ]);

    $approvedDocument = Document::factory()->approved()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'title' => 'Approved agreement',
        'updated_at' => now()->subMinutes(3),
    ]);

    $failedDocument = Document::factory()->scanFailed()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'title' => 'Failed scan',
        'updated_at' => now()->subMinutes(4),
    ]);

    Document::factory()->classificationFailed()->create([
        'tenant_id' => $otherTenant->id,
        'matter_id' => $otherMatter->id,
        'title' => 'Other tenant failure',
        'updated_at' => now()->addMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('realtimeTenantId', $tenant->id)
            ->has('pipelineDocuments', 5)
            ->where('pipelineDocuments.0.id', $latestDocument->id)
            ->where('pipelineDocuments.0.title', 'Latest upload')
            ->where('pipelineDocuments.0.status', 'uploaded')
            ->where('pipelineDocuments.0.matter_title', $matter->title)
            ->has('pipelineDocuments.0.updated_at')
            ->where('pipelineDocuments.1.id', $pendingReviewDocument->id)
            ->where('pipelineDocuments.1.status', 'ready_for_review')
            ->where('pipelineDocuments.2.id', $reviewedDocument->id)
            ->where('pipelineDocuments.2.status', 'reviewed')
            ->where('pipelineDocuments.3.id', $approvedDocument->id)
            ->where('pipelineDocuments.3.status', 'approved')
            ->where('pipelineDocuments.4.id', $failedDocument->id)
            ->where('pipelineDocuments.4.status', 'scan_failed')
            ->where('stats.processed_today', 1)
            ->where('stats.pending_review', 2)
            ->where('stats.failed', 1)
            ->has('recentFailures', 1)
            ->where('recentFailures.0.id', $failedDocument->id)
            ->where('recentFailures.0.status', 'scan_failed')
        );
});

test('dashboard falls back to empty snapshot for super admins without tenant context', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSuperAdmin($tenant);

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('realtimeTenantId', null)
            ->where('pipelineDocuments', [])
            ->where('stats.processed_today', 0)
            ->where('stats.pending_review', 0)
            ->where('stats.failed', 0)
            ->where('recentFailures', [])
        );
});

test('dashboard uses the selected tenant context for super admin metrics', function (): void {
    $tenant = Tenant::factory()->create();
    $selectedTenant = Tenant::factory()->create();
    $superAdmin = createSuperAdmin($tenant);
    $selectedMatter = createDashboardMatter($selectedTenant);
    $defaultMatter = createDashboardMatter($tenant);

    $selectedDocument = Document::factory()->classifying()->create([
        'tenant_id' => $selectedTenant->id,
        'matter_id' => $selectedMatter->id,
        'title' => 'Selected tenant document',
        'updated_at' => now(),
    ]);

    $selectedFailure = Document::factory()->extractionFailed()->create([
        'tenant_id' => $selectedTenant->id,
        'matter_id' => $selectedMatter->id,
        'title' => 'Selected tenant failure',
        'updated_at' => now()->subMinute(),
    ]);

    Document::factory()->approved()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $defaultMatter->id,
        'title' => 'Default tenant approval',
        'updated_at' => now()->addMinute(),
    ]);

    $this->actingAs($superAdmin)
        ->withSession([tenantContextSessionKey() => $selectedTenant->id])
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('realtimeTenantId', $selectedTenant->id)
            ->has('pipelineDocuments', 2)
            ->where('pipelineDocuments.0.id', $selectedDocument->id)
            ->where('pipelineDocuments.0.status', 'classifying')
            ->where('pipelineDocuments.1.id', $selectedFailure->id)
            ->where('pipelineDocuments.1.status', 'extraction_failed')
            ->where('stats.processed_today', 0)
            ->where('stats.pending_review', 0)
            ->where('stats.failed', 1)
            ->has('recentFailures', 1)
            ->where('recentFailures.0.id', $selectedFailure->id)
            ->where('recentFailures.0.status', 'extraction_failed')
        );
});

test('dashboard partial reload returns only stats and recent documents', function (): void {
    $tenant = Tenant::factory()->create();
    $user = createTenantAdmin($tenant);
    $matter = createDashboardMatter($tenant);

    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'title' => 'Reload target',
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('realtimeTenantId', $tenant->id)
            ->where('stats.pending_review', 1)
            ->has('pipelineDocuments', 1)
            ->where('pipelineDocuments.0.id', $document->id)
            ->where('recentFailures', [])
            ->reloadOnly(['pipelineDocuments', 'stats', 'recentFailures'], fn (Assert $reload) => $reload
                ->component('Dashboard')
                ->has('pipelineDocuments', 1)
                ->where('pipelineDocuments.0.id', $document->id)
                ->where('stats.pending_review', 1)
                ->where('recentFailures', [])
                ->missing('realtimeTenantId')
            )
        );
});

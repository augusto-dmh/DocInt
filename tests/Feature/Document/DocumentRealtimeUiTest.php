<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\ProcessingEvent;
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

function createDocumentRealtimeUiContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = createTenantAdmin($tenant);
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    return [$tenant, $user, $matter];
}

test('document show includes processing activity ordered from newest to oldest', function (): void {
    [$tenant, $user, $matter] = createDocumentRealtimeUiContext();

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $olderEvent = ProcessingEvent::factory()->create([
        'document_id' => $document->id,
        'tenant_id' => $tenant->id,
        'consumer_name' => 'ocr-extraction',
        'status_from' => 'scan_passed',
        'status_to' => 'extracting',
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    $newerEvent = ProcessingEvent::factory()->create([
        'document_id' => $document->id,
        'tenant_id' => $tenant->id,
        'consumer_name' => 'classification',
        'status_from' => 'extracting',
        'status_to' => 'classifying',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('document.id', $document->id)
            ->has('processingActivity', 2)
            ->where('processingActivity.0.id', $newerEvent->id)
            ->where('processingActivity.0.consumer_name', 'classification')
            ->where('processingActivity.0.status_from', 'extracting')
            ->where('processingActivity.0.status_to', 'classifying')
            ->where('processingActivity.1.id', $olderEvent->id)
            ->where('processingActivity.1.consumer_name', 'ocr-extraction')
        );
});

test('documents index partial reload returns only documents prop', function (): void {
    [$tenant, $user, $matter] = createDocumentRealtimeUiContext();

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.index'));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $document->id)
            ->has('documentExperience')
            ->reloadOnly(['documents'], fn (Assert $reload) => $reload
                ->component('documents/Index')
                ->has('documents.data', 1)
                ->where('documents.data.0.id', $document->id)
                ->missing('documentExperience')
            )
        );
});

test('document show partial reload returns only document activity props', function (): void {
    [$tenant, $user, $matter] = createDocumentRealtimeUiContext();

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $processingEvent = ProcessingEvent::factory()->create([
        'document_id' => $document->id,
        'tenant_id' => $tenant->id,
        'consumer_name' => 'virus-scan',
        'status_from' => 'uploaded',
        'status_to' => 'scanning',
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document));

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('document.id', $document->id)
            ->has('recentActivity')
            ->has('processingActivity', 1)
            ->where('reviewWorkspace.preview.supported', true)
            ->where('extractedData', null)
            ->where('classification', null)
            ->where('processingActivity.0.id', $processingEvent->id)
            ->has('documentExperience')
            ->reloadOnly(
                [
                    'document',
                    'recentActivity',
                    'processingActivity',
                    'reviewWorkspace',
                    'extractedData',
                    'classification',
                ],
                fn (Assert $reload) => $reload
                    ->component('documents/Show')
                    ->where('document.id', $document->id)
                    ->has('recentActivity')
                    ->has('processingActivity', 1)
                    ->where('reviewWorkspace.preview.supported', true)
                    ->where('extractedData', null)
                    ->where('classification', null)
                    ->where('processingActivity.0.id', $processingEvent->id)
                    ->missing('documentExperience')
            )
        );
});

test('background realtime refresh does not create an extra viewed audit log', function (): void {
    [$tenant, $user, $matter] = createDocumentRealtimeUiContext();

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful();

    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'viewed')
        ->count())->toBe(1);

    $response->assertInertia(fn (Assert $page) => $page
        ->reloadOnly(
            [
                'document',
                'recentActivity',
                'processingActivity',
                'reviewWorkspace',
                'extractedData',
                'classification',
            ],
            fn (Assert $reload) => $reload
                ->component('documents/Show')
                ->where('document.id', $document->id)
                ->has('recentActivity')
                ->has('processingActivity')
                ->where('reviewWorkspace.preview.supported', true)
                ->where('extractedData', null)
                ->where('classification', null)
                ->missing('documentExperience')
        )
    );

    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'viewed')
        ->count())->toBe(1);
});

<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function createDocumentCrudTestContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');
    setPermissionsTeamId(null);

    return [$tenant, $user, $matter];
}

test('document index page can be rendered', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $document->id)
            ->where('documents.data.0.matter.id', $matter->id)
            ->where('documents.data.0.uploader.id', $user->id)
        );
});

test('document create page can be rendered', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.documents.create', $matter))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Create')
            ->where('matter.id', $matter->id)
            ->where('matter.title', $matter->title)
        );
});

test('document can be stored', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    Storage::fake('s3');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [
            'title' => 'Retainer Agreement',
            'file' => UploadedFile::fake()->create('retainer.pdf', 256, 'application/pdf'),
        ]);

    $document = Document::query()->firstWhere('title', 'Retainer Agreement');

    $response->assertRedirect(route('documents.show', $document));
    expect($document)->not()->toBeNull()
        ->and($document->tenant_id)->toBe($tenant->id)
        ->and($document->matter_id)->toBe($matter->id)
        ->and(str_starts_with($document->file_path, "tenants/{$tenant->id}/documents/{$document->id}/"))->toBeTrue();

    Storage::disk('s3')->assertExists($document->file_path);
    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'uploaded')
        ->exists())->toBeTrue();
});

test('document store validates required fields', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [])
        ->assertSessionHasErrors(['title', 'file']);
});

test('document store validates file mime type and size', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    Storage::fake('s3');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [
            'title' => 'Invalid',
            'file' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ])
        ->assertSessionHasErrors(['file']);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [
            'title' => 'Too Large',
            'file' => UploadedFile::fake()->create('huge.pdf', 102401, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['file']);
});

test('document show page can be rendered and logs a view event', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('document.id', $document->id)
            ->where('document.matter.id', $matter->id)
            ->where('document.uploader.id', $user->id)
            ->where('reviewWorkspace.preview.supported', true)
            ->where('reviewWorkspace.preview.url', route('documents.preview', $document))
            ->where('reviewWorkspace.preview.fileName', $document->file_name)
            ->where('reviewWorkspace.preview.mimeType', 'application/pdf')
            ->where('extractedData', null)
            ->where('classification', null)
            ->has('recentActivity', 1)
            ->where('recentActivity.0.action', 'viewed')
        );

    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'viewed')
        ->exists())->toBeTrue();
});

test('document show page includes extracted data and classification evidence payloads', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_name' => 'engagement-letter.pdf',
        'mime_type' => null,
    ]);

    $extractedData = ExtractedData::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'extracted_text' => 'Signed engagement letter for litigation support.',
        'payload' => [
            'document_number' => 'ENG-204',
            'lines' => ['Signed engagement letter', 'Litigation support'],
        ],
        'metadata' => [
            'language' => 'en',
            'pages' => 2,
        ],
    ]);

    $classification = DocumentClassification::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'type' => 'contract',
        'confidence' => 0.9821,
        'metadata' => [
            'model' => 'gpt-5-mini',
        ],
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('reviewWorkspace.preview.supported', true)
            ->where('reviewWorkspace.preview.url', route('documents.preview', $document))
            ->where('extractedData.provider', $extractedData->provider)
            ->where('extractedData.extracted_text', $extractedData->extracted_text)
            ->where('extractedData.payload.document_number', 'ENG-204')
            ->where('extractedData.payload.lines.0', 'Signed engagement letter')
            ->where('extractedData.metadata.language', 'en')
            ->where('classification.provider', $classification->provider)
            ->where('classification.type', 'contract')
            ->where('classification.confidence', 0.9821)
            ->where('classification.metadata.model', 'gpt-5-mini')
        );
});

test('document show page marks non pdf documents as unsupported for inline preview', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_name' => 'notes.txt',
        'mime_type' => 'text/plain',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('reviewWorkspace.preview.supported', false)
            ->where('reviewWorkspace.preview.url', null)
            ->where('reviewWorkspace.preview.fileName', 'notes.txt')
            ->where('reviewWorkspace.preview.mimeType', 'text/plain')
        );
});

test('document can be marked as reviewed from ready for review', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.review', $document))
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->status->value)->toBe('reviewed')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'reviewed')
            ->exists())->toBeTrue();
});

test('document can be rejected from ready for review', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.reject', $document))
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->status->value)->toBe('rejected')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'rejected')
            ->exists())->toBeTrue();
});

test('document can be approved from reviewed', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->reviewed()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.approve', $document))
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->status->value)->toBe('approved')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'approved')
            ->exists())->toBeTrue();
});

test('document can be rejected from reviewed', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->reviewed()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.reject', $document))
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()->status->value)->toBe('rejected')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'rejected')
            ->exists())->toBeTrue();
});

test('document review transition validation fails for invalid status', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'uploaded',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.review', $document))
        ->assertSessionHasErrors(['status']);

    expect($document->fresh()->status->value)->toBe('uploaded');
});

test('document cannot be approved directly from ready for review', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.approve', $document))
        ->assertSessionHasErrors(['status']);

    expect($document->fresh()->status->value)->toBe('ready_for_review');
});

test('document preview streams inline pdf content for authorized tenant users', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    Storage::fake('s3');

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_path' => "tenants/{$tenant->id}/documents/{$matter->id}/reviewable.pdf",
        'file_name' => 'reviewable.pdf',
        'mime_type' => 'application/pdf',
    ]);

    Storage::disk('s3')->put($document->file_path, '%PDF-1.4 test document');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.preview', $document));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))->toContain('inline;')
        ->toContain('reviewable.pdf');
});

test('document preview denies cross tenant access', function (): void {
    [$tenant, $user] = createDocumentCrudTestContext();
    $otherTenant = Tenant::factory()->create();
    $otherMatter = Matter::factory()->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => Client::factory()->create(['tenant_id' => $otherTenant->id])->id,
    ]);
    $document = Document::factory()->create([
        'tenant_id' => $otherTenant->id,
        'matter_id' => $otherMatter->id,
        'file_name' => 'cross-tenant.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.preview', $document))
        ->assertNotFound();
});

test('document preview returns not found for unsupported or missing preview sources', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    Storage::fake('s3');

    $unsupportedDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_name' => 'notes.txt',
        'mime_type' => 'text/plain',
    ]);

    $missingPdfDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_path' => "tenants/{$tenant->id}/documents/404/missing.pdf",
        'file_name' => 'missing.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.preview', $unsupportedDocument))
        ->assertNotFound();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.preview', $missingPdfDocument))
        ->assertNotFound();
});

test('document edit page can be rendered', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.edit', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Edit')
            ->where('document.id', $document->id)
            ->where('document.title', $document->title)
        );
});

test('document can be updated', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'title' => 'Original Title',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->put(route('documents.update', $document), [
            'title' => 'Updated Title',
        ])
        ->assertRedirect(route('documents.show', $document));

    $updatedAuditLog = AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'updated')
        ->latest('id')
        ->first();

    expect($document->fresh()->title)->toBe('Updated Title')
        ->and($updatedAuditLog)->not()->toBeNull()
        ->and($updatedAuditLog?->metadata)->toMatchArray([
            'changes' => [
                'title' => [
                    'from' => 'Original Title',
                    'to' => 'Updated Title',
                ],
            ],
        ]);
});

test('document can be destroyed and file is removed from s3', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    Storage::fake('s3');

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_path' => "tenants/{$tenant->id}/documents/999/witness-statement.pdf",
    ]);

    Storage::disk('s3')->put($document->file_path, 'document body');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->delete(route('documents.destroy', $document))
        ->assertRedirect(route('documents.index'));

    expect(Document::query()->find($document->id))->toBeNull()
        ->and(Storage::disk('s3')->exists($document->file_path))->toBeFalse()
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'deleted')
            ->exists())->toBeTrue();
});

test('document download redirects to a presigned url and logs event', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $downloadUrl = 'https://example.test/download/document';

    $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
        $mock->shouldReceive('generatePresignedUrl')
            ->once()
            ->withArgs(function (Document $resolvedDocument) use ($document): bool {
                return $resolvedDocument->id === $document->id;
            })
            ->andReturn($downloadUrl);
    });

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.download', $document))
        ->assertRedirect($downloadUrl);

    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'downloaded')
        ->exists())->toBeTrue();
});

test('failed document upload removes pending records and audit logs', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudTestContext();
    tenancy()->initialize($tenant);

    Storage::shouldReceive('disk')
        ->once()
        ->with('s3')
        ->andReturnSelf();
    Storage::shouldReceive('putFileAs')
        ->once()
        ->andReturn(false);

    expect(fn (): Document => app(DocumentUploadService::class)->upload(
        UploadedFile::fake()->create('retainer.pdf', 256, 'application/pdf'),
        $matter,
        $user,
        'Retainer Agreement',
    ))->toThrow(\RuntimeException::class, 'Failed to store document on S3.');

    expect(Document::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
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

function createDocumentCrudContext(): array
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
    [$tenant, $user, $matter] = createDocumentCrudContext();
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
    [$tenant, $user, $matter] = createDocumentCrudContext();

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
    [$tenant, $user, $matter] = createDocumentCrudContext();
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
    [$tenant, $user, $matter] = createDocumentCrudContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [])
        ->assertSessionHasErrors(['title', 'file']);
});

test('document store validates file mime type and size', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudContext();
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
    [$tenant, $user, $matter] = createDocumentCrudContext();
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
            ->has('recentActivity', 1)
            ->where('recentActivity.0.action', 'viewed')
        );

    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'viewed')
        ->exists())->toBeTrue();
});

test('document edit page can be rendered', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudContext();
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
    [$tenant, $user, $matter] = createDocumentCrudContext();
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

    expect($document->fresh()->title)->toBe('Updated Title');
});

test('document can be destroyed and file is removed from s3', function (): void {
    [$tenant, $user, $matter] = createDocumentCrudContext();
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
    [$tenant, $user, $matter] = createDocumentCrudContext();
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

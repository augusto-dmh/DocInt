<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

function createDocumentAuthorizationContext(string $role): array
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
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return [$tenant, $user, $matter, $document];
}

describe('tenant-admin', function (): void {
    test('can access document CRUD routes', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthorizationContext('tenant-admin');
        Storage::fake('s3');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.index'))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.documents.create', $matter))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('matters.documents.store', $matter), [
                'title' => 'Tenant Admin Upload',
                'file' => UploadedFile::fake()->create('tenant-admin.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.show', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.edit', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('documents.update', $document), ['title' => 'Updated'])
            ->assertRedirect(route('documents.show', $document));

        $deletableDocument = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
            'uploaded_by' => $user->id,
            'file_path' => 'tenants/'.$tenant->id.'/documents/cleanup/delete-me.pdf',
        ]);
        Storage::disk('s3')->put($deletableDocument->file_path, 'delete me');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('documents.destroy', $deletableDocument))
            ->assertRedirect(route('documents.index'));
    });

    test('can download documents', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthorizationContext('tenant-admin');
        $downloadUrl = 'https://example.test/tenant-admin-document';

        $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->withArgs(fn (Document $resolvedDocument): bool => $resolvedDocument->is($document))
                ->andReturn($downloadUrl);
        });

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.download', $document))
            ->assertRedirect($downloadUrl);
    });

    test('can review approve and reject documents', function (): void {
        [$tenant, $user, $matter] = createDocumentAuthorizationContext('tenant-admin');

        $readyForReviewDocument = Document::factory()->readyForReview()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
        ]);
        $reviewedDocument = Document::factory()->reviewed()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.review', $readyForReviewDocument))
            ->assertRedirect(route('documents.show', $readyForReviewDocument));

        $reviewedDocumentAfterReview = $readyForReviewDocument->fresh();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.approve', $reviewedDocument))
            ->assertRedirect(route('documents.show', $reviewedDocument));

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.reject', $reviewedDocumentAfterReview))
            ->assertRedirect(route('documents.show', $readyForReviewDocument));
    });

    test('can create annotations and delete another users annotation', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthorizationContext('tenant-admin');

        $author = User::factory()->forTenant($tenant)->create();
        setPermissionsTeamId($tenant->id);
        $author->assignRole('associate');
        setPermissionsTeamId(null);

        $annotation = DocumentAnnotation::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'user_id' => $author->id,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->postJson(route('documents.annotations.store', $document), [
                'type' => 'highlight',
                'page_number' => 1,
                'coordinates' => [
                    'x' => 0.1,
                    'y' => 0.1,
                    'width' => 0.2,
                    'height' => 0.1,
                ],
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->deleteJson(route('documents.annotations.destroy', [$document, $annotation]))
            ->assertSuccessful();
    });
});

describe('partner', function (): void {
    test('can view create edit and update but cannot delete documents', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthorizationContext('partner');
        Storage::fake('s3');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.index'))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.documents.create', $matter))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.show', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.edit', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('documents.update', $document), ['title' => 'Partner Update'])
            ->assertRedirect(route('documents.show', $document));

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();
    });

    test('can download documents', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthorizationContext('partner');
        $downloadUrl = 'https://example.test/partner-document';

        $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->withArgs(fn (Document $resolvedDocument): bool => $resolvedDocument->is($document))
                ->andReturn($downloadUrl);
        });

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.download', $document))
            ->assertRedirect($downloadUrl);
    });

    test('can review approve and reject documents', function (): void {
        [$tenant, $user, $matter] = createDocumentAuthorizationContext('partner');

        $readyForReviewDocument = Document::factory()->readyForReview()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
        ]);
        $reviewedDocument = Document::factory()->reviewed()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.review', $readyForReviewDocument))
            ->assertRedirect(route('documents.show', $readyForReviewDocument));

        $reviewedDocumentAfterReview = $readyForReviewDocument->fresh();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.approve', $reviewedDocument))
            ->assertRedirect(route('documents.show', $reviewedDocument));

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.reject', $reviewedDocumentAfterReview))
            ->assertRedirect(route('documents.show', $readyForReviewDocument));
    });

    test('can create annotations and delete another users annotation', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthorizationContext('partner');

        $author = User::factory()->forTenant($tenant)->create();
        setPermissionsTeamId($tenant->id);
        $author->assignRole('associate');
        setPermissionsTeamId(null);

        $annotation = DocumentAnnotation::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'user_id' => $author->id,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->postJson(route('documents.annotations.store', $document), [
                'type' => 'comment',
                'page_number' => 1,
                'coordinates' => [
                    'x' => 0.1,
                    'y' => 0.1,
                    'width' => 0.2,
                    'height' => 0.1,
                ],
                'content' => 'Needs closer review.',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->deleteJson(route('documents.annotations.destroy', [$document, $annotation]))
            ->assertSuccessful();
    });
});

describe('associate', function (): void {
    test('can view create edit and update but cannot delete documents', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthorizationContext('associate');
        Storage::fake('s3');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.index'))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.documents.create', $matter))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.show', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.edit', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('documents.update', $document), ['title' => 'Associate Update'])
            ->assertRedirect(route('documents.show', $document));

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();
    });

    test('can download documents', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthorizationContext('associate');
        $downloadUrl = 'https://example.test/associate-document';

        $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->withArgs(fn (Document $resolvedDocument): bool => $resolvedDocument->is($document))
                ->andReturn($downloadUrl);
        });

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.download', $document))
            ->assertRedirect($downloadUrl);
    });

    test('can review and reject documents but cannot approve them', function (): void {
        [$tenant, $user, $matter] = createDocumentAuthorizationContext('associate');

        $readyForReviewDocument = Document::factory()->readyForReview()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
        ]);
        $reviewedDocument = Document::factory()->reviewed()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.review', $readyForReviewDocument))
            ->assertRedirect(route('documents.show', $readyForReviewDocument));

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.reject', $reviewedDocument))
            ->assertRedirect(route('documents.show', $reviewedDocument));

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.approve', Document::factory()->reviewed()->create([
                'tenant_id' => $tenant->id,
                'matter_id' => $matter->id,
            ])))
            ->assertForbidden();
    });

    test('can create annotations and delete only own annotations', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthorizationContext('associate');

        $ownAnnotation = DocumentAnnotation::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'user_id' => $user->id,
        ]);

        $otherUser = User::factory()->forTenant($tenant)->create();
        setPermissionsTeamId($tenant->id);
        $otherUser->assignRole('partner');
        setPermissionsTeamId(null);

        $otherAnnotation = DocumentAnnotation::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->postJson(route('documents.annotations.store', $document), [
                'type' => 'note',
                'page_number' => 1,
                'coordinates' => [
                    'x' => 0.1,
                    'y' => 0.1,
                    'width' => 0.2,
                    'height' => 0.1,
                ],
                'content' => 'Flag this region.',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->deleteJson(route('documents.annotations.destroy', [$document, $ownAnnotation]))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->deleteJson(route('documents.annotations.destroy', [$document, $otherAnnotation]))
            ->assertForbidden();
    });
});

describe('client role', function (): void {
    test('can view but cannot create edit update or delete documents', function (): void {
        [$tenant, $user, $matter, $document] = createDocumentAuthorizationContext('client');
        Storage::fake('s3');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.index'))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.show', $document))
            ->assertSuccessful();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.documents.create', $matter))
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('matters.documents.store', $matter), [
                'title' => 'Client Upload',
                'file' => UploadedFile::fake()->create('client.pdf', 12, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.edit', $document))
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('documents.update', $document), ['title' => 'Forbidden'])
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();
    });

    test('can download documents', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthorizationContext('client');
        $downloadUrl = 'https://example.test/client-document';

        $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
            $mock->shouldReceive('generatePresignedUrl')
                ->once()
                ->withArgs(fn (Document $resolvedDocument): bool => $resolvedDocument->is($document))
                ->andReturn($downloadUrl);
        });

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('documents.download', $document))
            ->assertRedirect($downloadUrl);
    });

    test('cannot review approve or reject documents', function (): void {
        [$tenant, $user, $matter] = createDocumentAuthorizationContext('client');

        $readyForReviewDocument = Document::factory()->readyForReview()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
        ]);
        $reviewedDocument = Document::factory()->reviewed()->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.review', $readyForReviewDocument))
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.approve', $reviewedDocument))
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('documents.reject', $readyForReviewDocument))
            ->assertForbidden();
    });

    test('cannot create annotations or delete another users annotation', function (): void {
        [$tenant, $user, , $document] = createDocumentAuthorizationContext('client');

        $otherUser = User::factory()->forTenant($tenant)->create();
        setPermissionsTeamId($tenant->id);
        $otherUser->assignRole('associate');
        setPermissionsTeamId(null);

        $annotation = DocumentAnnotation::factory()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->postJson(route('documents.annotations.store', $document), [
                'type' => 'highlight',
                'page_number' => 1,
                'coordinates' => [
                    'x' => 0.1,
                    'y' => 0.1,
                    'width' => 0.2,
                    'height' => 0.1,
                ],
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->deleteJson(route('documents.annotations.destroy', [$document, $annotation]))
            ->assertForbidden();
    });
});

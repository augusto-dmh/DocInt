<?php

use App\Models\AuditLog;
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

function createDocumentAnnotationContext(string $role = 'tenant-admin'): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'mime_type' => 'application/pdf',
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return [$tenant, $user, $matter, $document];
}

test('document show payload includes existing annotations', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext();

    DocumentAnnotation::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $user->id,
        'type' => 'note',
        'page_number' => 2,
        'content' => 'Capture the side letter reference.',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('reviewWorkspace.annotations.0.type', 'note')
            ->where('reviewWorkspace.annotations.0.page_number', 2)
            ->where('reviewWorkspace.annotations.0.is_owner', true)
            ->where('reviewWorkspace.permissions.canAnnotate', true)
        );
});

test('can annotate is false for client users', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext('client');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviewWorkspace.permissions.canAnnotate', false)
        );
});

test('can annotate is false for non pdf documents', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext();
    $document->update([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_name' => 'notes.docx',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviewWorkspace.permissions.canAnnotate', false)
        );
});

dataset('annotation-types', [
    'highlight' => ['highlight', null],
    'comment' => ['comment', 'Confirm the termination clause wording.'],
    'note' => ['note', 'Check if the appendix is missing.'],
]);

test('authorized user can create annotations', function (
    string $type,
    ?string $content,
): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.annotations.store', $document), [
            'type' => $type,
            'page_number' => 2,
            'coordinates' => [
                'x' => 0.12,
                'y' => 0.2,
                'width' => 0.26,
                'height' => 0.11,
            ],
            'content' => $content,
        ]);

    $response->assertCreated()
        ->assertJsonPath('annotation.type', $type)
        ->assertJsonPath('annotation.page_number', 2)
        ->assertJsonPath('activity.action', 'annotation_created');

    expect(DocumentAnnotation::query()->where('document_id', $document->id)->count())->toBe(1)
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'annotation_created')
            ->exists())->toBeTrue();
})->with('annotation-types');

test('annotation create validates conditional content', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.annotations.store', $document), [
            'type' => 'comment',
            'page_number' => 1,
            'coordinates' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.08,
            ],
            'content' => '',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);
});

test('annotation create validates normalized coordinates', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.annotations.store', $document), [
            'type' => 'highlight',
            'page_number' => 1,
            'coordinates' => [
                'x' => 0.85,
                'y' => 0.22,
                'width' => 0.2,
                'height' => 0.1,
            ],
            'content' => null,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['coordinates']);
});

test('annotation create requires inline preview support', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext();
    $document->update([
        'mime_type' => 'text/plain',
        'file_name' => 'notes.txt',
    ]);

    $response = $this->actingAs($user)
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
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['document']);
});

test('annotation author can delete own annotation', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext();

    $annotation = DocumentAnnotation::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->deleteJson(route('documents.annotations.destroy', [$document, $annotation]));

    $response->assertSuccessful()
        ->assertJsonPath('annotation_id', $annotation->id)
        ->assertJsonPath('activity.action', 'annotation_deleted');

    expect(DocumentAnnotation::query()->find($annotation->id))->toBeNull()
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'annotation_deleted')
            ->exists())->toBeTrue();
});

test('user with approve documents can delete another users annotation', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext('partner');

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
        ->deleteJson(route('documents.annotations.destroy', [$document, $annotation]))
        ->assertSuccessful();

    expect(DocumentAnnotation::query()->find($annotation->id))->toBeNull();
});

test('associate without approve permission cannot delete another users annotation', function (): void {
    [$tenant, $user, , $document] = createDocumentAnnotationContext('associate');

    $author = User::factory()->forTenant($tenant)->create();
    setPermissionsTeamId($tenant->id);
    $author->assignRole('partner');
    setPermissionsTeamId(null);

    $annotation = DocumentAnnotation::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $author->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->deleteJson(route('documents.annotations.destroy', [$document, $annotation]))
        ->assertForbidden();

    expect(DocumentAnnotation::query()->find($annotation->id))->not()->toBeNull();
});

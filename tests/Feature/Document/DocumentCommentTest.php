<?php

use App\Events\DocumentCommentUpdated;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
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

function createDocumentCommentContext(string $role = 'associate'): array
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

function createTenantCommentUserWithRole(Tenant $tenant, string $role, ?string $name = null): User
{
    $user = User::factory()->forTenant($tenant)->create(
        $name !== null ? ['name' => $name] : [],
    );

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return $user;
}

test('document show payload includes comments and comment permissions', function (): void {
    [$tenant, $user, , $document] = createDocumentCommentContext('partner');

    $rootComment = DocumentComment::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $user->id,
        'body' => 'Review the indemnity clause wording.',
    ]);

    $replyAuthor = createTenantCommentUserWithRole($tenant, 'associate', 'Morgan Reviewer');

    DocumentComment::factory()->replyTo($rootComment->id)->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $replyAuthor->id,
        'body' => 'I think the carve-out belongs in the schedule.',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('reviewWorkspace.permissions.canComment', true)
            ->where('reviewWorkspace.permissions.canModerateComments', true)
            ->has('reviewWorkspace.comments', 2)
            ->where('reviewWorkspace.comments.0.parent_id', null)
            ->where('reviewWorkspace.comments.0.body', 'Review the indemnity clause wording.')
            ->where('reviewWorkspace.comments.1.parent_id', $rootComment->id)
        );
});

test('client users cannot comment and do not receive comment permissions', function (): void {
    [$tenant, $user, , $document] = createDocumentCommentContext('client');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviewWorkspace.permissions.canComment', false)
            ->where('reviewWorkspace.permissions.canModerateComments', false)
        );

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.comments.store', $document), [
            'body' => 'Clients should not be able to post here.',
        ])
        ->assertForbidden();
});

dataset('commenting-roles', [
    'tenant-admin' => 'tenant-admin',
    'partner' => 'partner',
    'associate' => 'associate',
]);

test('authorized users can create top level comments and replies', function (string $role): void {
    Event::fake([DocumentCommentUpdated::class]);

    [$tenant, $user, , $document] = createDocumentCommentContext($role);

    $createResponse = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.comments.store', $document), [
            'body' => 'Please confirm the governing law section.',
        ]);

    $createResponse->assertCreated()
        ->assertJsonPath('comment.parent_id', null)
        ->assertJsonPath('activity.action', 'comment_created');

    /** @var DocumentComment $comment */
    $comment = DocumentComment::query()->latest('id')->firstOrFail();

    $replyResponse = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.comments.store', $document), [
            'body' => 'Following up with the schedule citation.',
            'parent_id' => $comment->id,
        ]);

    $replyResponse->assertCreated()
        ->assertJsonPath('comment.parent_id', $comment->id);

    expect($document->comments()->count())->toBe(2)
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'comment_created')
            ->count())->toBe(2);

    Event::assertDispatched(DocumentCommentUpdated::class, function (DocumentCommentUpdated $event) use ($document, $tenant): bool {
        $payload = $event->broadcastWith();

        return $event->broadcastAs() === 'document.comment.updated'
            && $payload['tenant_id'] === $tenant->id
            && $payload['document_id'] === $document->id
            && $payload['action'] === 'created'
            && is_array($payload['comment'])
            && is_array($payload['activity']);
    });
})->with('commenting-roles');

test('comment creation rejects parent comments from another document', function (): void {
    [$tenant, $user, $matter, $document] = createDocumentCommentContext('associate');

    $otherDocument = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
    ]);

    $otherComment = DocumentComment::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $otherDocument->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.comments.store', $document), [
            'body' => 'This reply should fail.',
            'parent_id' => $otherComment->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'parent_id' => 'The selected parent comment must belong to this document.',
        ]);
});

test('comment index returns tenant scoped comments for the document', function (): void {
    [$tenant, $user, , $document] = createDocumentCommentContext('associate');

    DocumentComment::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $user->id,
        'body' => 'First thread entry.',
    ]);

    $otherTenant = Tenant::factory()->create();
    $otherMatter = Matter::factory()->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => Client::factory()->create(['tenant_id' => $otherTenant->id])->id,
    ]);
    $otherDocument = Document::factory()->readyForReview()->create([
        'tenant_id' => $otherTenant->id,
        'matter_id' => $otherMatter->id,
    ]);

    DocumentComment::factory()->create([
        'tenant_id' => $otherTenant->id,
        'document_id' => $otherDocument->id,
        'user_id' => User::factory()->forTenant($otherTenant)->create()->id,
        'body' => 'Cross tenant comment.',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->getJson(route('documents.comments.index', $document))
        ->assertSuccessful()
        ->assertJsonCount(1, 'comments')
        ->assertJsonPath('comments.0.body', 'First thread entry.');
});

test('comment author can update own comment', function (): void {
    Event::fake([DocumentCommentUpdated::class]);

    [$tenant, $user, , $document] = createDocumentCommentContext('associate');

    $comment = DocumentComment::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $user->id,
        'body' => 'Original wording.',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->patchJson(route('documents.comments.update', [$document, $comment]), [
            'body' => 'Updated wording after review.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('comment.body', 'Updated wording after review.')
        ->assertJsonPath('activity.action', 'comment_updated');

    expect($comment->fresh()?->body)->toBe('Updated wording after review.')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'comment_updated')
            ->exists())->toBeTrue();

    Event::assertDispatched(DocumentCommentUpdated::class, fn (DocumentCommentUpdated $event): bool => $event->broadcastWith()['action'] === 'updated');
});

test('users cannot update another users comment', function (): void {
    [$tenant, $user, , $document] = createDocumentCommentContext('partner');
    $author = createTenantCommentUserWithRole($tenant, 'associate');

    $comment = DocumentComment::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $author->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->patchJson(route('documents.comments.update', [$document, $comment]), [
            'body' => 'Partners should not rewrite other users comments.',
        ])
        ->assertForbidden();
});

test('comment author can delete own comment', function (): void {
    Event::fake([DocumentCommentUpdated::class]);

    [$tenant, $user, , $document] = createDocumentCommentContext('associate');

    $comment = DocumentComment::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $user->id,
        'body' => 'Delete this draft note.',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->deleteJson(route('documents.comments.destroy', [$document, $comment]))
        ->assertSuccessful()
        ->assertJsonPath('comment_id', $comment->id)
        ->assertJsonPath('activity.action', 'comment_deleted');

    expect(DocumentComment::query()->find($comment->id))->toBeNull();

    Event::assertDispatched(DocumentCommentUpdated::class, fn (DocumentCommentUpdated $event): bool => $event->broadcastWith()['action'] === 'deleted');
});

test('moderators can delete another users comment', function (): void {
    [$tenant, $user, , $document] = createDocumentCommentContext('partner');
    $author = createTenantCommentUserWithRole($tenant, 'associate');

    $comment = DocumentComment::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $author->id,
        'body' => 'Moderator can remove this.',
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->deleteJson(route('documents.comments.destroy', [$document, $comment]))
        ->assertSuccessful();

    expect(DocumentComment::query()->find($comment->id))->toBeNull();
});

test('non moderators cannot delete another users comment', function (): void {
    [$tenant, $user, , $document] = createDocumentCommentContext('associate');
    $author = createTenantCommentUserWithRole($tenant, 'partner');

    $comment = DocumentComment::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $author->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->deleteJson(route('documents.comments.destroy', [$document, $comment]))
        ->assertForbidden();
});

test('cross tenant comment routes return not found', function (): void {
    [$tenant, $user, , $document] = createDocumentCommentContext('associate');
    $otherTenant = Tenant::factory()->create();
    $otherDocument = Document::factory()->readyForReview()->create([
        'tenant_id' => $otherTenant->id,
        'matter_id' => Matter::factory()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => Client::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ])->id,
    ]);

    $comment = DocumentComment::factory()->create([
        'tenant_id' => $otherTenant->id,
        'document_id' => $otherDocument->id,
        'user_id' => User::factory()->forTenant($otherTenant)->create()->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->deleteJson(route('documents.comments.destroy', [$document, $comment]))
        ->assertNotFound();
});

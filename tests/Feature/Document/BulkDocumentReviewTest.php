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
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function createBulkDocumentReviewContext(string $role = 'partner'): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return [$tenant, $user, $matter];
}

function createBulkReviewDocument(Tenant $tenant, Matter $matter, User $user, string $state = 'readyForReview'): Document
{
    return Document::factory()
        ->{$state}()
        ->create([
            'tenant_id' => $tenant->id,
            'matter_id' => $matter->id,
            'uploaded_by' => $user->id,
        ]);
}

function createBulkReviewAssociate(Tenant $tenant, string $name): User
{
    $associate = User::factory()->forTenant($tenant)->create([
        'name' => $name,
    ]);

    setPermissionsTeamId($tenant->id);
    $associate->assignRole('associate');
    setPermissionsTeamId(null);

    return $associate;
}

test('document index exposes bulk review permissions and reviewer options for authorized users', function (): void {
    [$tenant, $user, $matter] = createBulkDocumentReviewContext('partner');
    createBulkReviewDocument($tenant, $matter, $user);
    $firstReviewer = createBulkReviewAssociate($tenant, 'Avery Associate');
    $secondReviewer = createBulkReviewAssociate($tenant, 'Morgan Reviewer');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->where('bulkReview.permissions.canBulkApprove', true)
            ->where('bulkReview.permissions.canBulkReject', true)
            ->where('bulkReview.permissions.canBulkReassign', true)
            ->has('bulkReview.availableReviewers', 2)
            ->where('bulkReview.availableReviewers.0.id', $firstReviewer->id)
            ->where('bulkReview.availableReviewers.1.id', $secondReviewer->id)
        );
});

test('document index hides bulk reassignment controls from associates', function (): void {
    [$tenant, $user, $matter] = createBulkDocumentReviewContext('associate');
    createBulkReviewDocument($tenant, $matter, $user);
    createBulkReviewAssociate($tenant, 'Avery Associate');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->where('bulkReview.permissions.canBulkApprove', false)
            ->where('bulkReview.permissions.canBulkReject', true)
            ->where('bulkReview.permissions.canBulkReassign', false)
            ->has('bulkReview.availableReviewers', 0)
        );
});

test('partners can bulk approve reviewed documents and skip invalid selections', function (): void {
    [$tenant, $user, $matter] = createBulkDocumentReviewContext('partner');
    $reviewedDocument = createBulkReviewDocument($tenant, $matter, $user, 'reviewed');
    $readyDocument = createBulkReviewDocument($tenant, $matter, $user, 'readyForReview');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.bulk.approve'), [
            'document_ids' => [$reviewedDocument->id, $readyDocument->id],
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'action' => 'approved',
            'attempted_count' => 2,
            'processed_count' => 1,
            'skipped_count' => 1,
            'processed_ids' => [$reviewedDocument->id],
            'message' => 'Approved 1 document. Skipped 1.',
        ])
        ->assertJsonPath('skipped.0.document_id', $readyDocument->id)
        ->assertJsonPath('skipped.0.reason', 'Document cannot transition from [ready_for_review] to [approved].');

    expect($reviewedDocument->fresh()?->status->value)->toBe('approved')
        ->and($readyDocument->fresh()?->status->value)->toBe('ready_for_review')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $reviewedDocument->id)
            ->where('action', 'approved')
            ->where('metadata->bulk_action', true)
            ->exists())->toBeTrue();
});

test('associates cannot bulk approve documents', function (): void {
    [$tenant, $user, $matter] = createBulkDocumentReviewContext('associate');
    $reviewedDocument = createBulkReviewDocument($tenant, $matter, $user, 'reviewed');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.bulk.approve'), [
            'document_ids' => [$reviewedDocument->id],
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'action' => 'approved',
            'attempted_count' => 1,
            'processed_count' => 0,
            'skipped_count' => 1,
            'processed_ids' => [],
            'message' => 'No documents were approved.',
        ])
        ->assertJsonPath('skipped.0.document_id', $reviewedDocument->id)
        ->assertJsonPath('skipped.0.reason', 'You are not allowed to approve this document.');

    expect($reviewedDocument->fresh()?->status->value)->toBe('reviewed');
});

test('partners can bulk reject eligible documents', function (): void {
    [$tenant, $user, $matter] = createBulkDocumentReviewContext('partner');
    $reviewedDocument = createBulkReviewDocument($tenant, $matter, $user, 'reviewed');
    $approvedDocument = createBulkReviewDocument($tenant, $matter, $user, 'approved');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson(route('documents.bulk.reject'), [
            'document_ids' => [$reviewedDocument->id, $approvedDocument->id],
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'action' => 'rejected',
            'attempted_count' => 2,
            'processed_count' => 1,
            'skipped_count' => 1,
            'processed_ids' => [$reviewedDocument->id],
            'message' => 'Rejected 1 document. Skipped 1.',
        ])
        ->assertJsonPath('skipped.0.document_id', $approvedDocument->id)
        ->assertJsonPath('skipped.0.reason', 'Document cannot transition from [approved] to [rejected].');

    expect($reviewedDocument->fresh()?->status->value)->toBe('rejected')
        ->and($approvedDocument->fresh()?->status->value)->toBe('approved')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $reviewedDocument->id)
            ->where('action', 'rejected')
            ->where('metadata->bulk_action', true)
            ->exists())->toBeTrue();
});

test('partners can bulk assign reviewers and skip unchanged assignments', function (): void {
    [$tenant, $user, $matter] = createBulkDocumentReviewContext('partner');
    $targetReviewer = createBulkReviewAssociate($tenant, 'Jordan Associate');
    $alreadyAssignedDocument = createBulkReviewDocument($tenant, $matter, $user);
    $unassignedDocument = createBulkReviewDocument($tenant, $matter, $user);

    $alreadyAssignedDocument->update([
        'assigned_to' => $targetReviewer->id,
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->patchJson(route('documents.bulk.reviewer.assign'), [
            'document_ids' => [$alreadyAssignedDocument->id, $unassignedDocument->id],
            'assigned_to' => $targetReviewer->id,
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'action' => 'reassign',
            'attempted_count' => 2,
            'processed_count' => 1,
            'skipped_count' => 1,
            'processed_ids' => [$unassignedDocument->id],
            'message' => 'Reassigned 1 document. Skipped 1.',
        ])
        ->assertJsonPath('skipped.0.document_id', $alreadyAssignedDocument->id)
        ->assertJsonPath('skipped.0.reason', 'Reviewer assignment is already up to date.');

    expect($unassignedDocument->fresh()?->assigned_to)->toBe($targetReviewer->id)
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $unassignedDocument->id)
            ->where('action', 'reviewer_assignment_updated')
            ->where('metadata->bulk_action', true)
            ->where('metadata->assignee_id', $targetReviewer->id)
            ->exists())->toBeTrue();
});

test('bulk reviewer assignment rejects non associate reviewers', function (): void {
    [$tenant, $user, $matter] = createBulkDocumentReviewContext('partner');
    $document = createBulkReviewDocument($tenant, $matter, $user);
    $invalidReviewer = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $invalidReviewer->assignRole('partner');
    setPermissionsTeamId(null);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->patchJson(route('documents.bulk.reviewer.assign'), [
            'document_ids' => [$document->id],
            'assigned_to' => $invalidReviewer->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'assigned_to' => 'The selected reviewer must be an associate.',
        ]);
});

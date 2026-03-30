<?php

use App\Events\DocumentStatusUpdated;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
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

function createDocumentReviewerAssignmentContext(string $role = 'partner'): array
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
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    return [$tenant, $user, $matter, $document];
}

function createTenantAssociate(Tenant $tenant, string $name): User
{
    $associate = User::factory()->forTenant($tenant)->create([
        'name' => $name,
    ]);

    setPermissionsTeamId($tenant->id);
    $associate->assignRole('associate');
    setPermissionsTeamId(null);

    return $associate;
}

test('document show payload includes assignee and reviewer options for authorized users', function (): void {
    [$tenant, $user, , $document] = createDocumentReviewerAssignmentContext('partner');

    $assignedReviewer = createTenantAssociate($tenant, 'Avery Associate');
    $candidateReviewer = createTenantAssociate($tenant, 'Morgan Reviewer');

    $document->update([
        'assigned_to' => $assignedReviewer->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('document.assignee.id', $assignedReviewer->id)
            ->where('document.assignee.name', $assignedReviewer->name)
            ->where('reviewWorkspace.permissions.canAssignReviewer', true)
            ->has('reviewWorkspace.availableReviewers', 2)
            ->where('reviewWorkspace.availableReviewers.0.id', $assignedReviewer->id)
            ->where('reviewWorkspace.availableReviewers.1.id', $candidateReviewer->id)
        );
});

test('associate users cannot assign reviewers or receive reviewer options', function (): void {
    [$tenant, $user, , $document] = createDocumentReviewerAssignmentContext('associate');
    $reviewer = createTenantAssociate($tenant, 'Riley Reviewer');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviewWorkspace.permissions.canAssignReviewer', false)
            ->has('reviewWorkspace.availableReviewers', 0)
        );

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->patch(route('documents.reviewer.assign', $document), [
            'assigned_to' => $reviewer->id,
        ])
        ->assertForbidden();
});

dataset('reviewer-assignment-roles', [
    'partner' => 'partner',
    'tenant-admin' => 'tenant-admin',
]);

test('authorized users can assign a tenant associate reviewer', function (string $role): void {
    Event::fake([DocumentStatusUpdated::class]);

    [$tenant, $user, , $document] = createDocumentReviewerAssignmentContext($role);
    $reviewer = createTenantAssociate($tenant, 'Jordan Associate');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->patch(route('documents.reviewer.assign', $document), [
            'assigned_to' => $reviewer->id,
        ])
        ->assertRedirect(route('documents.show', $document));

    expect($document->fresh()?->assigned_to)->toBe($reviewer->id);

    $auditLog = AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'reviewer_assignment_updated')
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog?->metadata['assignee_name'] ?? null)->toBe($reviewer->name);

    Event::assertDispatched(DocumentStatusUpdated::class, function (DocumentStatusUpdated $event) use ($document, $tenant): bool {
        $payload = $event->broadcastWith();

        return $payload['tenant_id'] === $tenant->id
            && $payload['document_id'] === $document->id
            && $payload['from_status'] === 'ready_for_review'
            && $payload['to_status'] === 'ready_for_review'
            && $payload['trace_id'] === $document->processing_trace_id;
    });
})->with('reviewer-assignment-roles');

test('authorized users can clear a reviewer assignment', function (): void {
    [$tenant, $user, , $document] = createDocumentReviewerAssignmentContext('partner');
    $reviewer = createTenantAssociate($tenant, 'Taylor Associate');

    $document->update([
        'assigned_to' => $reviewer->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->patch(route('documents.reviewer.assign', $document), [
            'assigned_to' => null,
        ])
        ->assertRedirect(route('documents.show', $document));

    $document->refresh();

    expect($document->assigned_to)->toBeNull();

    $auditLog = AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'reviewer_assignment_updated')
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog?->metadata['previous_assignee_name'] ?? null)->toBe($reviewer->name)
        ->and($auditLog?->metadata['assignee_name'] ?? null)->toBeNull();
});

test('reviewer assignment rejects non associate users in the same tenant', function (): void {
    [$tenant, $user, , $document] = createDocumentReviewerAssignmentContext('partner');

    $nonAssociate = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $nonAssociate->assignRole('partner');
    setPermissionsTeamId(null);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->from(route('documents.show', $document))
        ->patch(route('documents.reviewer.assign', $document), [
            'assigned_to' => $nonAssociate->id,
        ])
        ->assertRedirect(route('documents.show', $document))
        ->assertSessionHasErrors([
            'assigned_to' => 'The selected reviewer must be an associate.',
        ]);
});

test('reviewer assignment rejects cross tenant users', function (): void {
    [$tenant, $user, , $document] = createDocumentReviewerAssignmentContext('partner');
    $otherTenant = Tenant::factory()->create();
    $otherTenantAssociate = createTenantAssociate($otherTenant, 'Casey Cross Tenant');

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->from(route('documents.show', $document))
        ->patch(route('documents.reviewer.assign', $document), [
            'assigned_to' => $otherTenantAssociate->id,
        ])
        ->assertRedirect(route('documents.show', $document))
        ->assertSessionHasErrors([
            'assigned_to' => 'The selected reviewer must belong to the active tenant.',
        ]);
});

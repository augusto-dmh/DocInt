<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\DocumentBulkReviewService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    tenancy()->end();
});

function makeBulkReviewContext(string $role = 'partner'): array
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

    tenancy()->initialize($tenant);
    setPermissionsTeamId($tenant->id);

    return [$tenant, $user, $matter];
}

test('performStatusTransition approves eligible documents and skips invalid transitions', function (): void {
    [$tenant, $user, $matter] = makeBulkReviewContext('partner');

    $reviewedDoc = Document::factory()->reviewed()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);
    $readyDoc = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    /** @var DocumentBulkReviewService $service */
    $service = app(DocumentBulkReviewService::class);

    $payload = $service->performStatusTransition(
        documentIds: [$reviewedDoc->id, $readyDoc->id],
        toStatus: DocumentStatus::Approved,
        ability: 'approve',
        authorizationVerb: 'approve',
        successAction: 'approved',
        actor: $user,
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($payload['action'])->toBe('approved')
        ->and($payload['attempted_count'])->toBe(2)
        ->and($payload['processed_count'])->toBe(1)
        ->and($payload['skipped_count'])->toBe(1)
        ->and($payload['processed_ids'])->toBe([$reviewedDoc->id])
        ->and($payload['skipped'][0]['document_id'])->toBe($readyDoc->id)
        ->and($payload['skipped'][0]['reason'])->toBe('Document cannot transition from [ready_for_review] to [approved].')
        ->and($payload['message'])->toBe('Approved 1 document. Skipped 1.');

    expect($reviewedDoc->fresh()?->status->value)->toBe('approved')
        ->and($readyDoc->fresh()?->status->value)->toBe('ready_for_review');
});

test('performStatusTransition skips documents the actor cannot authorize', function (): void {
    [$tenant, $user, $matter] = makeBulkReviewContext('associate');

    $reviewedDoc = Document::factory()->reviewed()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    /** @var DocumentBulkReviewService $service */
    $service = app(DocumentBulkReviewService::class);

    $payload = $service->performStatusTransition(
        documentIds: [$reviewedDoc->id],
        toStatus: DocumentStatus::Approved,
        ability: 'approve',
        authorizationVerb: 'approve',
        successAction: 'approved',
        actor: $user,
    );

    expect($payload['processed_count'])->toBe(0)
        ->and($payload['skipped_count'])->toBe(1)
        ->and($payload['skipped'][0]['reason'])->toBe('You are not allowed to approve this document.')
        ->and($payload['message'])->toBe('No documents were approved.');
});

test('performStatusTransition reports unknown ids as skipped', function (): void {
    [, $user] = makeBulkReviewContext('partner');

    /** @var DocumentBulkReviewService $service */
    $service = app(DocumentBulkReviewService::class);

    $payload = $service->performStatusTransition(
        documentIds: [999999],
        toStatus: DocumentStatus::Approved,
        ability: 'approve',
        authorizationVerb: 'approve',
        successAction: 'approved',
        actor: $user,
    );

    expect($payload['skipped_count'])->toBe(1)
        ->and($payload['skipped'][0]['document_id'])->toBe(999999)
        ->and($payload['skipped'][0]['title'])->toBeNull()
        ->and($payload['skipped'][0]['reason'])->toBe('Document is no longer available.');
});

test('performStatusTransition writes a bulk_action audit entry per processed document', function (): void {
    [$tenant, $user, $matter] = makeBulkReviewContext('partner');

    $reviewedDoc = Document::factory()->reviewed()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    /** @var DocumentBulkReviewService $service */
    $service = app(DocumentBulkReviewService::class);

    $service->performStatusTransition(
        documentIds: [$reviewedDoc->id],
        toStatus: DocumentStatus::Approved,
        ability: 'approve',
        authorizationVerb: 'approve',
        successAction: 'approved',
        actor: $user,
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $reviewedDoc->id)
        ->where('action', 'approved')
        ->where('metadata->bulk_action', true)
        ->exists())->toBeTrue();
});

test('performReviewerAssignment assigns associate to documents and skips unchanged assignees', function (): void {
    [$tenant, $user, $matter] = makeBulkReviewContext('partner');

    $reviewer = User::factory()->forTenant($tenant)->create(['name' => 'Jordan Associate']);
    setPermissionsTeamId($tenant->id);
    $reviewer->assignRole('associate');
    setPermissionsTeamId($tenant->id);

    $alreadyAssigned = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'assigned_to' => $reviewer->id,
    ]);
    $unassigned = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    /** @var DocumentBulkReviewService $service */
    $service = app(DocumentBulkReviewService::class);

    $payload = $service->performReviewerAssignment(
        documentIds: [$alreadyAssigned->id, $unassigned->id],
        assignedTo: $reviewer->id,
        actor: $user,
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($payload['action'])->toBe('reassign')
        ->and($payload['processed_count'])->toBe(1)
        ->and($payload['skipped_count'])->toBe(1)
        ->and($payload['processed_ids'])->toBe([$unassigned->id])
        ->and($payload['skipped'][0]['document_id'])->toBe($alreadyAssigned->id)
        ->and($payload['skipped'][0]['reason'])->toBe('Reviewer assignment is already up to date.')
        ->and($payload['message'])->toBe('Reassigned 1 document. Skipped 1.');

    expect($unassigned->fresh()?->assigned_to)->toBe($reviewer->id);
});

test('performReviewerAssignment skips documents the actor cannot reassign', function (): void {
    [$tenant, $user, $matter] = makeBulkReviewContext('associate');

    $reviewer = User::factory()->forTenant($tenant)->create();
    setPermissionsTeamId($tenant->id);
    $reviewer->assignRole('associate');
    setPermissionsTeamId($tenant->id);

    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    /** @var DocumentBulkReviewService $service */
    $service = app(DocumentBulkReviewService::class);

    $payload = $service->performReviewerAssignment(
        documentIds: [$document->id],
        assignedTo: $reviewer->id,
        actor: $user,
    );

    expect($payload['processed_count'])->toBe(0)
        ->and($payload['skipped'][0]['reason'])->toBe('You are not allowed to reassign this document.');
});

test('resolveAssignee returns null for non-numeric input', function (): void {
    makeBulkReviewContext();

    /** @var DocumentBulkReviewService $service */
    $service = app(DocumentBulkReviewService::class);

    expect($service->resolveAssignee(null))->toBeNull()
        ->and($service->resolveAssignee(''))->toBeNull();
});

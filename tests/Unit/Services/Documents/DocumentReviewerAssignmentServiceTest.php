<?php

declare(strict_types=1);

use App\Events\DocumentStatusUpdated;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\DocumentReviewerAssignmentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

function makeReviewerAssignmentDocument(): array
{
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
    ]);

    return [$tenant, $document];
}

function makeAssociate(Tenant $tenant, string $name): User
{
    $associate = User::factory()->forTenant($tenant)->create(['name' => $name]);

    setPermissionsTeamId($tenant->id);
    $associate->assignRole('associate');
    setPermissionsTeamId(null);

    return $associate;
}

test('availableReviewersForTenant returns only associates of the tenant ordered by name', function (): void {
    [$tenant] = makeReviewerAssignmentDocument();
    $alpha = makeAssociate($tenant, 'Avery Associate');
    $beta = makeAssociate($tenant, 'Morgan Reviewer');

    $partner = User::factory()->forTenant($tenant)->create(['name' => 'Pat Partner']);
    setPermissionsTeamId($tenant->id);
    $partner->assignRole('partner');
    setPermissionsTeamId(null);

    $otherTenant = Tenant::factory()->create();
    makeAssociate($otherTenant, 'Casey Cross');

    $service = new DocumentReviewerAssignmentService;

    $reviewers = $service->availableReviewersForTenant($tenant->id);

    expect($reviewers)->toBe([
        ['id' => $alpha->id, 'name' => $alpha->name],
        ['id' => $beta->id, 'name' => $beta->name],
    ]);
});

test('availableReviewersForAssignment delegates to the document tenant', function (): void {
    [$tenant, $document] = makeReviewerAssignmentDocument();
    $associate = makeAssociate($tenant, 'Riley Reviewer');

    $service = new DocumentReviewerAssignmentService;

    expect($service->availableReviewersForAssignment($document))
        ->toBe([['id' => $associate->id, 'name' => $associate->name]]);
});

test('resolveAssignee returns null for non-numeric values', function (): void {
    [, $document] = makeReviewerAssignmentDocument();

    $service = new DocumentReviewerAssignmentService;

    expect($service->resolveAssignee(null, $document))->toBeNull()
        ->and($service->resolveAssignee('', $document))->toBeNull();
});

test('resolveAssignee scopes lookups to the document tenant', function (): void {
    [$tenant, $document] = makeReviewerAssignmentDocument();
    $associate = makeAssociate($tenant, 'Jordan Associate');

    $service = new DocumentReviewerAssignmentService;

    expect($service->resolveAssignee($associate->id, $document)?->id)->toBe($associate->id);
});

test('resolveAssignee throws when the user belongs to a different tenant', function (): void {
    [, $document] = makeReviewerAssignmentDocument();
    $otherTenant = Tenant::factory()->create();
    $crossTenantUser = User::factory()->forTenant($otherTenant)->create();

    $service = new DocumentReviewerAssignmentService;

    $service->resolveAssignee($crossTenantUser->id, $document);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('assign updates the document, refreshes the relation, and broadcasts a status event', function (): void {
    Event::fake([DocumentStatusUpdated::class]);

    [$tenant, $document] = makeReviewerAssignmentDocument();
    $associate = makeAssociate($tenant, 'Taylor Associate');

    $service = new DocumentReviewerAssignmentService;

    $result = $service->assign($document, $associate);

    expect($result['changed'])->toBeTrue()
        ->and($result['previous_assignee_id'])->toBeNull()
        ->and($result['previous_assignee_name'])->toBeNull()
        ->and($result['assignee_id'])->toBe($associate->id)
        ->and($result['assignee_name'])->toBe($associate->name)
        ->and($result['document']->assigned_to)->toBe($associate->id);

    Event::assertDispatched(DocumentStatusUpdated::class, function (DocumentStatusUpdated $event) use ($document): bool {
        $payload = $event->broadcastWith();

        return $payload['document_id'] === $document->id
            && $payload['from_status'] === $payload['to_status'];
    });
});

test('assign is a no-op when the assignee is unchanged', function (): void {
    Event::fake([DocumentStatusUpdated::class]);

    [$tenant, $document] = makeReviewerAssignmentDocument();
    $associate = makeAssociate($tenant, 'Quinn Associate');

    $document->update(['assigned_to' => $associate->id]);

    $service = new DocumentReviewerAssignmentService;

    $result = $service->assign($document->fresh() ?? $document, $associate);

    expect($result['changed'])->toBeFalse();

    Event::assertNotDispatched(DocumentStatusUpdated::class);
});

test('assign clears the reviewer when null is passed and previous assignee was set', function (): void {
    Event::fake([DocumentStatusUpdated::class]);

    [$tenant, $document] = makeReviewerAssignmentDocument();
    $associate = makeAssociate($tenant, 'Casey Associate');

    $document->update(['assigned_to' => $associate->id]);

    $service = new DocumentReviewerAssignmentService;

    $result = $service->assign($document->fresh() ?? $document, null);

    expect($result['changed'])->toBeTrue()
        ->and($result['previous_assignee_id'])->toBe($associate->id)
        ->and($result['previous_assignee_name'])->toBe($associate->name)
        ->and($result['assignee_id'])->toBeNull()
        ->and($result['assignee_name'])->toBeNull()
        ->and($result['document']->assigned_to)->toBeNull();

    Event::assertDispatched(DocumentStatusUpdated::class);
});

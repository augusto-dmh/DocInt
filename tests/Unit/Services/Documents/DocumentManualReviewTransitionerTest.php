<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\DocumentManualReviewTransitioner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

function makeManualReviewContext(string $state = 'readyForReview'): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->{$state}()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    tenancy()->initialize($tenant);

    return [$tenant, $user, $document];
}

test('transition advances the document via the status transition service and writes an audit log', function (): void {
    [, $user, $document] = makeManualReviewContext('readyForReview');

    /** @var DocumentManualReviewTransitioner $transitioner */
    $transitioner = app(DocumentManualReviewTransitioner::class);

    $transitioned = $transitioner->transition(
        document: $document,
        toStatus: DocumentStatus::Reviewed,
        actor: $user,
        ipAddress: '127.0.0.1',
        userAgent: 'phpunit',
    );

    expect($transitioned->status)->toBe(DocumentStatus::Reviewed)
        ->and($document->fresh()?->status)->toBe(DocumentStatus::Reviewed);

    $log = AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'reviewed')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log?->user_id)->toBe($user->id)
        ->and($log?->metadata['ip_address'] ?? null)->toBe('127.0.0.1')
        ->and($log?->metadata['user_agent'] ?? null)->toBe('phpunit');
});

test('transition raises ValidationException when the state machine forbids the move', function (): void {
    [, $user, $document] = makeManualReviewContext('readyForReview');

    /** @var DocumentManualReviewTransitioner $transitioner */
    $transitioner = app(DocumentManualReviewTransitioner::class);

    expect(fn () => $transitioner->transition(
        document: $document,
        toStatus: DocumentStatus::Approved,
        actor: $user,
    ))->toThrow(
        ValidationException::class,
    );

    expect($document->fresh()?->status)->toBe(DocumentStatus::ReadyForReview);
});

test('ValidationException carries a status-keyed message describing the rejected transition', function (): void {
    [, $user, $document] = makeManualReviewContext('readyForReview');

    /** @var DocumentManualReviewTransitioner $transitioner */
    $transitioner = app(DocumentManualReviewTransitioner::class);

    try {
        $transitioner->transition(
            document: $document,
            toStatus: DocumentStatus::Approved,
            actor: $user,
        );

        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        $errors = $exception->errors();

        expect($errors)->toHaveKey('status')
            ->and($errors['status'][0] ?? null)->toBe('Document cannot transition from [ready_for_review] to [approved].');
    }
});

test('transition uses manual-review as the consumer name on the recorded processing event', function (): void {
    [, $user, $document] = makeManualReviewContext('readyForReview');

    /** @var DocumentManualReviewTransitioner $transitioner */
    $transitioner = app(DocumentManualReviewTransitioner::class);

    $transitioner->transition(
        document: $document,
        toStatus: DocumentStatus::Reviewed,
        actor: $user,
    );

    expect($document->processingEvents()->where('consumer_name', 'manual-review')->exists())->toBeTrue();
});

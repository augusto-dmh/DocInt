<?php

use App\Events\DocumentStatusUpdated;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentStatusTransitionService;
use App\Services\DocumentUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

afterEach(function (): void {
    tenancy()->end();
});

function createBroadcastDocumentContext(): array
{
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $user = User::factory()->forTenant($tenant)->create();

    return [$tenant, $matter, $user];
}

function broadcastChannelNames(DocumentStatusUpdated $event): array
{
    return array_map(
        static fn (object $channel): string => $channel->name,
        $event->broadcastOn(),
    );
}

test('document upload dispatches a document status updated broadcast event', function (): void {
    Event::fake([DocumentStatusUpdated::class]);
    Storage::fake('s3');

    [$tenant, $matter, $user] = createBroadcastDocumentContext();

    tenancy()->initialize($tenant);

    $document = app(DocumentUploadService::class)->upload(
        UploadedFile::fake()->create('engagement-letter.pdf', 128, 'application/pdf'),
        $matter,
        $user,
        'Engagement Letter',
    );

    Event::assertDispatched(DocumentStatusUpdated::class, function (DocumentStatusUpdated $event) use ($document, $tenant): bool {
        $payload = $event->broadcastWith();

        return $event->broadcastAs() === 'document.status.updated'
            && $payload['tenant_id'] === $tenant->id
            && $payload['document_id'] === $document->id
            && $payload['from_status'] === null
            && $payload['to_status'] === 'uploaded'
            && is_string($payload['trace_id'])
            && $payload['trace_id'] !== ''
            && broadcastChannelNames($event) === [
                "private-tenants.{$tenant->id}.documents",
                "private-documents.{$document->id}",
            ];
    });
});

test('document status transitions dispatch a document status updated broadcast event', function (): void {
    Event::fake([DocumentStatusUpdated::class]);

    [$tenant, $matter] = createBroadcastDocumentContext();

    tenancy()->initialize($tenant);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'status' => 'uploaded',
        'processing_trace_id' => (string) Str::uuid(),
    ]);

    app(DocumentStatusTransitionService::class)->transition(
        document: $document,
        toStatus: 'scanning',
        consumerName: 'broadcast-test',
    );

    Event::assertDispatched(DocumentStatusUpdated::class, function (DocumentStatusUpdated $event) use ($document, $tenant): bool {
        $payload = $event->broadcastWith();

        return $payload['tenant_id'] === $tenant->id
            && $payload['document_id'] === $document->id
            && $payload['from_status'] === 'uploaded'
            && $payload['to_status'] === 'scanning'
            && $payload['trace_id'] === $document->processing_trace_id
            && broadcastChannelNames($event) === [
                "private-tenants.{$tenant->id}.documents",
                "private-documents.{$document->id}",
            ];
    });
});

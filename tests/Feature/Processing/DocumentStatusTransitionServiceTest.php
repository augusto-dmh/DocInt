<?php

use App\Enums\DocumentStatus;
use App\Events\DocumentProcessingEvent;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;
use App\Services\DocumentStatusTransitionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

afterEach(function (): void {
    tenancy()->end();
});

function createProcessingDocument(DocumentStatus $status = DocumentStatus::Uploaded): Document
{
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    return Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'status' => $status,
        'processing_trace_id' => null,
    ]);
}

test('valid transition advances status and records transition event', function (): void {
    Event::fake([DocumentProcessingEvent::class]);
    $document = createProcessingDocument();
    $messageId = (string) Str::uuid();

    $updatedDocument = app(DocumentStatusTransitionService::class)->transition(
        document: $document,
        toStatus: DocumentStatus::Scanning,
        consumerName: 'virus-scan',
        messageId: $messageId,
        metadata: ['source' => 'test-suite'],
    );

    $processingEvent = ProcessingEvent::query()->firstWhere('document_id', $document->id);

    expect($updatedDocument->status)->toBe(DocumentStatus::Scanning)
        ->and($updatedDocument->processing_trace_id)->not()->toBeNull()
        ->and(Str::isUuid($updatedDocument->processing_trace_id))->toBeTrue()
        ->and($processingEvent)->not()->toBeNull()
        ->and($processingEvent?->status_from)->toBe(DocumentStatus::Uploaded->value)
        ->and($processingEvent?->status_to)->toBe(DocumentStatus::Scanning->value)
        ->and($processingEvent?->message_id)->toBe($messageId);

    Event::assertDispatched(DocumentProcessingEvent::class, function (DocumentProcessingEvent $event) use ($document, $messageId): bool {
        return $event->documentId === $document->id
            && $event->tenantId === $document->tenant_id
            && $event->event === 'document.status.transitioned'
            && $event->messageId === $messageId;
    });
});

test('invalid transition is rejected and leaves status unchanged', function (): void {
    Event::fake([DocumentProcessingEvent::class]);
    $document = createProcessingDocument(DocumentStatus::Uploaded);

    expect(fn () => app(DocumentStatusTransitionService::class)->transition(
        document: $document,
        toStatus: DocumentStatus::Approved,
    ))->toThrow(InvalidArgumentException::class);

    expect($document->fresh()->status)->toBe(DocumentStatus::Uploaded)
        ->and(ProcessingEvent::query()->count())->toBe(0);
});

test('terminal states reject further transitions', function (): void {
    Event::fake([DocumentProcessingEvent::class]);
    $document = createProcessingDocument(DocumentStatus::Approved);

    expect(fn () => app(DocumentStatusTransitionService::class)->transition(
        document: $document,
        toStatus: DocumentStatus::Reviewed,
    ))->toThrow(InvalidArgumentException::class);

    expect($document->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and(ProcessingEvent::query()->count())->toBe(0);
});

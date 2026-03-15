<?php

use App\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;
use App\Services\ProcessingEventRecorder;
use Illuminate\Support\Str;

afterEach(function (): void {
    tenancy()->end();
});

test('processing event recorder is idempotent for duplicate message and consumer keys', function (): void {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'status' => DocumentStatus::Uploaded,
        'processing_trace_id' => (string) Str::uuid(),
    ]);
    $messageId = (string) Str::uuid();

    $recorder = app(ProcessingEventRecorder::class);

    $firstRecord = $recorder->record(
        document: $document,
        messageId: $messageId,
        consumerName: 'ocr-extraction',
        event: 'document.status.transitioned',
        statusFrom: DocumentStatus::Uploaded,
        statusTo: DocumentStatus::Scanning,
        traceId: $document->processing_trace_id,
        metadata: ['attempt' => 1],
    );

    $secondRecord = $recorder->record(
        document: $document,
        messageId: $messageId,
        consumerName: 'ocr-extraction',
        event: 'document.status.transitioned',
        statusFrom: DocumentStatus::Scanning,
        statusTo: DocumentStatus::ScanPassed,
        traceId: $document->processing_trace_id,
        metadata: ['attempt' => 2],
    );

    $storedRecord = ProcessingEvent::query()->sole();

    expect($firstRecord->id)->toBe($secondRecord->id)
        ->and(ProcessingEvent::query()->count())->toBe(1)
        ->and($storedRecord->status_from)->toBe(DocumentStatus::Uploaded->value)
        ->and($storedRecord->status_to)->toBe(DocumentStatus::Scanning->value)
        ->and($storedRecord->metadata)->toBe(['attempt' => 1]);
});

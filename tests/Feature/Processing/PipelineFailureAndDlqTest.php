<?php

use App\Jobs\Processing\ClassificationConsumerJob;
use App\Jobs\Processing\DeadLetterConsumerJob;
use App\Jobs\Processing\OcrExtractionConsumerJob;
use App\Jobs\Processing\VirusScanConsumerJob;
use App\Models\Client;
use App\Models\Document;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;
use App\Services\DocumentStatusTransitionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

afterEach(function (): void {
    tenancy()->end();
});

function createFailureDocument(string $status, string $fileName = 'document.pdf'): Document
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
        'file_name' => $fileName,
        'processing_trace_id' => (string) Str::uuid(),
    ]);
}

/**
 * @param  array<string, mixed>  $metadata
 * @return array<string, mixed>
 */
function failurePayload(Document $document, array $metadata = []): array
{
    return [
        'message_id' => (string) Str::uuid(),
        'trace_id' => (string) Str::uuid(),
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'event' => 'document.uploaded',
        'timestamp' => now()->toISOString(),
        'metadata' => $metadata,
        'retry_count' => 0,
    ];
}

function runJobAndTriggerFailedHook(object $job): void
{
    try {
        app()->call([$job, 'handle']);
    } catch (Throwable $exception) {
        if (method_exists($job, 'failed')) {
            $job->failed($exception);
        }
    }
}

test('consumer rejects payload without a valid message_id and sends it to DLQ', function (): void {
    Queue::fake();

    $document = createFailureDocument('uploaded', 'no-message-id.pdf');
    $payload = failurePayload($document);
    unset($payload['message_id']);

    runJobAndTriggerFailedHook(new VirusScanConsumerJob($payload));

    $deadLetterJob = Queue::pushed(DeadLetterConsumerJob::class)->first();

    expect($deadLetterJob)->toBeInstanceOf(DeadLetterConsumerJob::class)
        ->and($deadLetterJob->payload['metadata']['failure_reason'] ?? null)->toContain('message_id');

    expect($document->fresh()->status->value)->toBe('uploaded');
});

test('ocr failures are dead lettered and move document to extraction failed', function (): void {
    Queue::fake();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'id' => 'ocr-invalid',
            'choices' => [
                [
                    'message' => [
                        'content' => 'not valid json',
                    ],
                ],
            ],
        ], 200),
    ]);

    $document = createFailureDocument('scan_passed', 'scan.pdf');
    $payload = failurePayload($document, ['ocr_source_text' => 'Source text for OCR']);

    runJobAndTriggerFailedHook(new OcrExtractionConsumerJob($payload));

    $deadLetterJob = Queue::pushed(DeadLetterConsumerJob::class)->first();

    expect($deadLetterJob)->toBeInstanceOf(DeadLetterConsumerJob::class)
        ->and($deadLetterJob->payload['metadata']['failed_consumer'] ?? null)->toBe('ocr-extraction')
        ->and($deadLetterJob->payload['metadata']['failure_reason'] ?? null)->toBeString();

    app()->call([$deadLetterJob, 'handle']);

    expect($document->fresh()->status->value)->toBe('extraction_failed')
        ->and(ProcessingEvent::query()->where('consumer_name', 'dead-letters')->count())->toBe(1);
});

test('classification failures are dead lettered and move document to classification failed', function (): void {
    Queue::fake();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'id' => 'classification-invalid',
            'choices' => [
                [
                    'message' => [
                        'content' => 'not valid json',
                    ],
                ],
            ],
        ], 200),
    ]);

    $document = createFailureDocument('classifying', 'contract.pdf');

    ExtractedData::query()->create([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'extracted_text' => 'Simulated text',
        'payload' => ['lines' => ['Simulated text']],
        'metadata' => ['classification_hint' => 'contract'],
    ]);

    $payload = failurePayload($document);

    runJobAndTriggerFailedHook(new ClassificationConsumerJob($payload));

    $deadLetterJob = Queue::pushed(DeadLetterConsumerJob::class)->first();

    expect($deadLetterJob)->toBeInstanceOf(DeadLetterConsumerJob::class)
        ->and($deadLetterJob->payload['metadata']['failed_consumer'] ?? null)->toBe('classification')
        ->and($deadLetterJob->payload['metadata']['failure_reason'] ?? null)->toBeString();

    app()->call([$deadLetterJob, 'handle']);

    expect($document->fresh()->status->value)->toBe('classification_failed')
        ->and(ProcessingEvent::query()->where('consumer_name', 'dead-letters')->count())->toBe(1);
});

test('virus scan simulated failure transitions document to scan failed', function (): void {
    $document = createFailureDocument('uploaded', 'malware.pdf');
    $payload = failurePayload($document, ['simulate_scan_failure' => true]);

    app()->call([new VirusScanConsumerJob($payload), 'handle']);

    $event = ProcessingEvent::query()
        ->where('document_id', $document->id)
        ->where('consumer_name', 'virus-scan')
        ->first();

    expect($document->fresh()->status->value)->toBe('scan_failed')
        ->and($event)->not()->toBeNull()
        ->and($event->event)->toBe('document.virus_scan.failed');
});

test('ocr provider degradation requeues extraction with metadata', function (): void {
    Queue::fake();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'error' => ['message' => 'ThrottlingException'],
        ], 429),
    ]);

    $document = createFailureDocument('scan_passed', 'degraded.pdf');
    $payload = failurePayload($document, ['ocr_source_text' => 'Source text for degraded OCR']);

    app()->call([new OcrExtractionConsumerJob($payload), 'handle']);

    $requeuedJob = Queue::pushed(OcrExtractionConsumerJob::class)->first();

    expect($requeuedJob)->toBeInstanceOf(OcrExtractionConsumerJob::class)
        ->and($requeuedJob->queue)->toBe('queue.ocr-extraction')
        ->and($requeuedJob->payload['metadata']['provider_degraded_attempt'] ?? null)->toBe(1)
        ->and($requeuedJob->payload['metadata']['provider_degraded_provider'] ?? null)->toBe('openai')
        ->and($requeuedJob->payload['metadata']['provider_degraded_reason'] ?? null)->toContain('ThrottlingException')
        ->and($requeuedJob->payload['retry_count'] ?? null)->toBe(1);
});

test('classification provider degradation requeues classification with metadata', function (): void {
    Queue::fake();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'error' => ['message' => 'Rate limit exceeded'],
        ], 429),
    ]);

    $document = createFailureDocument('classifying', 'degraded-contract.pdf');

    ExtractedData::query()->create([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'extracted_text' => 'Simulated text',
        'payload' => ['lines' => ['Simulated text']],
        'metadata' => ['classification_hint' => 'contract'],
    ]);

    $payload = failurePayload($document);

    app()->call([new ClassificationConsumerJob($payload), 'handle']);

    $requeuedJob = Queue::pushed(ClassificationConsumerJob::class)->first();

    expect($requeuedJob)->toBeInstanceOf(ClassificationConsumerJob::class)
        ->and($requeuedJob->queue)->toBe('queue.classify.contract')
        ->and($requeuedJob->payload['metadata']['provider_degraded_attempt'] ?? null)->toBe(1)
        ->and($requeuedJob->payload['metadata']['provider_degraded_provider'] ?? null)->toBe('openai')
        ->and($requeuedJob->payload['metadata']['provider_degraded_reason'] ?? null)->toContain('Rate limit exceeded')
        ->and($requeuedJob->payload['retry_count'] ?? null)->toBe(1);
});

test('ocr degraded retries exhaustion dead letters and transitions to extraction failed', function (): void {
    Queue::fake();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'error' => ['message' => 'ServiceUnavailableException'],
        ], 429),
    ]);

    $document = createFailureDocument('scan_passed', 'degraded-exhaustion.pdf');
    $payload = failurePayload($document, [
        'ocr_source_text' => 'Source text for degraded exhaustion OCR',
        'provider_degraded_attempt' => 3,
    ]);

    runJobAndTriggerFailedHook(new OcrExtractionConsumerJob($payload));

    $deadLetterJob = Queue::pushed(DeadLetterConsumerJob::class)->first();

    expect($deadLetterJob)->toBeInstanceOf(DeadLetterConsumerJob::class)
        ->and($deadLetterJob->payload['metadata']['failed_consumer'] ?? null)->toBe('ocr-extraction')
        ->and($deadLetterJob->payload['metadata']['failure_reason'] ?? null)->toContain('remained degraded');

    app()->call([$deadLetterJob, 'handle']);

    expect($document->fresh()->status->value)->toBe('extraction_failed');
});

test('virus scan handles concurrent transition gracefully without dead lettering', function (): void {
    Queue::fake();

    $document = createFailureDocument('uploaded', 'concurrent-scan.pdf');
    $payload = failurePayload($document);

    $this->partialMock(DocumentStatusTransitionService::class, function ($mock): void {
        $mock->shouldReceive('transition')
            ->once()
            ->andThrow(new InvalidArgumentException('Transition rejected by concurrent worker'));
    });

    app()->call([new VirusScanConsumerJob($payload), 'handle']);

    Queue::assertNothingPushed();
});

test('ocr extraction handles concurrent transition gracefully without dead lettering', function (): void {
    Queue::fake();
    Http::fake();

    $document = createFailureDocument('scan_passed', 'concurrent-ocr.pdf');
    $payload = failurePayload($document, ['ocr_source_text' => 'Sample source text']);

    $this->partialMock(DocumentStatusTransitionService::class, function ($mock): void {
        $mock->shouldReceive('transition')
            ->once()
            ->andThrow(new InvalidArgumentException('Transition rejected by concurrent worker'));
    });

    app()->call([new OcrExtractionConsumerJob($payload), 'handle']);

    Queue::assertNothingPushed();
});

test('classification degraded retries exhaustion dead letters and transitions to classification failed', function (): void {
    Queue::fake();
    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'error' => ['message' => 'Service unavailable'],
        ], 429),
    ]);

    $document = createFailureDocument('classifying', 'degraded-exhaustion-contract.pdf');

    ExtractedData::query()->create([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'extracted_text' => 'Simulated text',
        'payload' => ['lines' => ['Simulated text']],
        'metadata' => ['classification_hint' => 'contract'],
    ]);

    $payload = failurePayload($document, ['provider_degraded_attempt' => 3]);

    runJobAndTriggerFailedHook(new ClassificationConsumerJob($payload));

    $deadLetterJob = Queue::pushed(DeadLetterConsumerJob::class)->first();

    expect($deadLetterJob)->toBeInstanceOf(DeadLetterConsumerJob::class)
        ->and($deadLetterJob->payload['metadata']['failed_consumer'] ?? null)->toBe('classification')
        ->and($deadLetterJob->payload['metadata']['failure_reason'] ?? null)->toContain('remained degraded');

    app()->call([$deadLetterJob, 'handle']);

    expect($document->fresh()->status->value)->toBe('classification_failed');
});

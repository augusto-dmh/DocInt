<?php

use App\Jobs\Processing\ClassificationConsumerJob;
use App\Jobs\Processing\OcrExtractionConsumerJob;
use App\Jobs\Processing\VirusScanConsumerJob;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

afterEach(function (): void {
    tenancy()->end();
});

function createPipelineDocument(string $status = 'uploaded', ?string $fileName = null): Document
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
        'file_name' => $fileName ?? 'contract.pdf',
        'processing_trace_id' => (string) Str::uuid(),
    ]);
}

/**
 * @param  array<string, mixed>  $metadata
 * @return array<string, mixed>
 */
function pipelinePayload(Document $document, array $metadata = []): array
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

/**
 * @param  list<array<string, mixed>>  $messages
 */
function fakeOpenAiResponses(array $messages): void
{
    $responses = array_map(
        static fn (array $payload) => Http::response($payload, 200),
        $messages,
    );

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::sequence($responses),
    ]);
}

test('pipeline consumers process a document end to end to ready for review', function (): void {
    Queue::fake();

    fakeOpenAiResponses([
        [
            'id' => 'ocr-response',
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'extracted_text' => 'Master services agreement for retained legal work.',
                            'classification_hint' => 'contract',
                            'lines' => ['Master services agreement', 'Retained legal work'],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ],
        [
            'id' => 'classification-response',
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'label' => 'contract',
                            'confidence' => 0.98,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ],
    ]);

    $document = createPipelineDocument('uploaded', 'msa-contract.pdf');
    $payload = pipelinePayload($document, [
        'classification_hint' => 'contract',
        'ocr_source_text' => 'Master services agreement for retained legal work.',
    ]);

    app()->call([new VirusScanConsumerJob($payload), 'handle']);

    expect($document->fresh()->status->value)->toBe('scan_passed');

    app()->call([new OcrExtractionConsumerJob($payload), 'handle']);

    $document->refresh();

    expect($document->status->value)->toBe('classifying')
        ->and(ExtractedData::query()->where('document_id', $document->id)->exists())->toBeTrue();

    $classificationJob = Queue::pushed(ClassificationConsumerJob::class)->first();

    expect($classificationJob)->toBeInstanceOf(ClassificationConsumerJob::class);

    app()->call([$classificationJob, 'handle']);

    $document->refresh();
    $classification = DocumentClassification::query()->firstWhere('document_id', $document->id);

    expect($document->status->value)->toBe('ready_for_review')
        ->and($classification)->not()->toBeNull()
        ->and($classification->type)->toBe('contract');

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('Authorization')
            && $request->url() === 'https://api.openai.com/v1/chat/completions';
    });

    expect(ProcessingEvent::query()->where('consumer_name', 'virus-scan')->count())->toBe(1)
        ->and(ProcessingEvent::query()->where('consumer_name', 'ocr-extraction')->count())->toBe(1)
        ->and(ProcessingEvent::query()->where('consumer_name', 'classification')->count())->toBe(1);
});

test('pipeline consumers remain idempotent when duplicate messages are redelivered', function (): void {
    Queue::fake();

    fakeOpenAiResponses([
        [
            'id' => 'ocr-response',
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'extracted_text' => 'Invoice for document review services.',
                            'classification_hint' => 'invoice',
                            'lines' => ['Invoice for document review services'],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ],
        [
            'id' => 'classification-response',
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'label' => 'invoice',
                            'confidence' => 0.94,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ],
    ]);

    $document = createPipelineDocument('uploaded', 'invoice.pdf');
    $payload = pipelinePayload($document, [
        'classification_hint' => 'invoice',
        'ocr_source_text' => 'Invoice for document review services.',
    ]);

    $virusScanJob = new VirusScanConsumerJob($payload);
    $ocrExtractionJob = new OcrExtractionConsumerJob($payload);

    app()->call([$virusScanJob, 'handle']);
    app()->call([$virusScanJob, 'handle']);

    app()->call([$ocrExtractionJob, 'handle']);
    app()->call([$ocrExtractionJob, 'handle']);

    $classificationJob = Queue::pushed(ClassificationConsumerJob::class)->first();

    expect($classificationJob)->toBeInstanceOf(ClassificationConsumerJob::class);

    app()->call([$classificationJob, 'handle']);
    app()->call([$classificationJob, 'handle']);

    Http::assertSentCount(2);

    expect(ProcessingEvent::query()->where('consumer_name', 'virus-scan')->count())->toBe(1)
        ->and(ProcessingEvent::query()->where('consumer_name', 'ocr-extraction')->count())->toBe(1)
        ->and(ProcessingEvent::query()->where('consumer_name', 'classification')->count())->toBe(1)
        ->and(ExtractedData::query()->where('document_id', $document->id)->count())->toBe(1)
        ->and(DocumentClassification::query()->where('document_id', $document->id)->count())->toBe(1)
        ->and($document->fresh()->status->value)->toBe('ready_for_review');
});

test('tenant mismatched payload does not mutate cross tenant document state', function (): void {
    Queue::fake();
    Http::fake();

    $allowedDocument = createPipelineDocument('uploaded', 'tax-form.pdf');
    $otherTenantDocument = createPipelineDocument('uploaded', 'nda.pdf');

    $payload = pipelinePayload($otherTenantDocument, ['ocr_source_text' => 'Non matching tenant payload']);
    $payload['tenant_id'] = $allowedDocument->tenant_id;

    app()->call([new VirusScanConsumerJob($payload), 'handle']);
    app()->call([new OcrExtractionConsumerJob($payload), 'handle']);

    expect($otherTenantDocument->fresh()->status->value)->toBe('uploaded')
        ->and($allowedDocument->fresh()->status->value)->toBe('uploaded')
        ->and(ExtractedData::query()->where('document_id', $otherTenantDocument->id)->exists())->toBeFalse();

    $tenantMismatchEvents = ProcessingEvent::query()
        ->where('document_id', $otherTenantDocument->id)
        ->whereIn('consumer_name', ['virus-scan-tenant-mismatch', 'ocr-extraction-tenant-mismatch'])
        ->count();

    expect($tenantMismatchEvents)->toBe(2);
});

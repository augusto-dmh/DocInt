<?php

namespace App\Jobs\Processing;

use App\Jobs\Processing\Concerns\InteractsWithProcessingPayload;
use App\Models\Document;
use App\Models\ExtractedData;
use App\Models\ProcessingEvent;
use App\Services\DocumentStatusTransitionService;
use App\Services\Processing\ClassificationRoutingResolver;
use App\Services\Processing\OcrProvider;
use App\Services\Processing\ProviderDegradedException;
use App\Services\ProcessingEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OcrExtractionConsumerJob implements ShouldQueue
{
    use InteractsWithProcessingPayload, Queueable;

    public int $tries;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
        $this->onConnection($this->resolveQueueConnection());
        $this->tries = $this->resolveRetryAttempts();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return $this->resolveRetryBackoff();
    }

    public function handle(
        DocumentStatusTransitionService $transitionService,
        ProcessingEventRecorder $processingEventRecorder,
        OcrProvider $ocrProvider,
        ClassificationRoutingResolver $classificationRoutingResolver,
    ): void {
        $documentId = $this->resolveDocumentId();
        $tenantId = $this->resolveTenantId();
        $messageId = $this->resolveMessageId();
        $traceId = $this->resolveTraceId();
        $metadata = $this->resolveMetadata();

        $document = Document::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($documentId)
            ->first();

        if ($document === null) {
            $this->recordTenantMismatch(
                consumerName: 'ocr-extraction',
                documentId: $documentId,
                tenantId: $tenantId,
                messageId: $messageId,
                traceId: $traceId,
                metadata: $metadata,
                processingEventRecorder: $processingEventRecorder,
            );

            return;
        }

        if ($this->isAlreadyProcessed($document, $messageId)) {
            return;
        }

        $statusBeforeProcessing = $this->resolveDocumentStatus($document);

        if (in_array($statusBeforeProcessing, ['uploaded', 'scanning'], true)) {
            $this->redispatchWhileScanPending();

            return;
        }

        if ($statusBeforeProcessing === 'scan_failed') {
            return;
        }

        if ($statusBeforeProcessing === 'scan_passed') {
            $document = $transitionService->transition(
                document: $document,
                toStatus: 'extracting',
                consumerName: 'ocr-extraction-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'ocr-extraction'],
            );
        }

        $document = $document->fresh();

        if ($document === null || ! in_array($this->resolveDocumentStatus($document), ['extracting', 'classifying', 'ready_for_review'], true)) {
            return;
        }

        $classificationHint = $classificationRoutingResolver->resolveTypeHint(
            is_string($metadata['classification_hint'] ?? null) ? $metadata['classification_hint'] : null,
            $document->file_name,
        );

        if ($this->resolveDocumentStatus($document) === 'extracting') {
            try {
                $result = $ocrProvider->extract($document, $this->payload);
            } catch (ProviderDegradedException $exception) {
                $this->redispatchWhileProviderDegraded($exception);

                return;
            }

            $classificationHint = $classificationRoutingResolver->resolveTypeHint(
                is_string($result['classification_hint'] ?? null) ? $result['classification_hint'] : null,
                $document->file_name,
                is_string($result['extracted_text'] ?? null) ? $result['extracted_text'] : null,
            );

            ExtractedData::query()->updateOrCreate(
                ['document_id' => $document->id],
                [
                    'tenant_id' => $document->tenant_id,
                    'provider' => (string) ($result['provider'] ?? 'openai'),
                    'extracted_text' => is_string($result['extracted_text'] ?? null)
                        ? $result['extracted_text']
                        : null,
                    'payload' => is_array($result['payload'] ?? null)
                        ? $result['payload']
                        : null,
                    'metadata' => [
                        'classification_hint' => $classificationHint,
                        'provider_metadata' => is_array($result['metadata'] ?? null)
                            ? $result['metadata']
                            : [],
                    ],
                ],
            );

            $document = $transitionService->transition(
                document: $document,
                toStatus: 'classifying',
                consumerName: 'ocr-extraction-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'ocr-extraction', 'classification_hint' => $classificationHint],
            );
        }

        $classificationPayload = $this->payload;
        $classificationPayload['event'] = 'document.extracted';
        $classificationPayload['metadata'] = array_merge($metadata, [
            'classification_hint' => $classificationHint,
        ]);

        ClassificationConsumerJob::dispatch($classificationPayload)
            ->onConnection($this->resolveQueueConnection())
            ->onQueue($classificationRoutingResolver->resolveQueueForType($classificationHint));

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'ocr-extraction',
            event: 'document.ocr.extracted',
            statusFrom: $statusBeforeProcessing,
            statusTo: $this->resolveDocumentStatus($document),
            traceId: $traceId,
            metadata: array_merge($metadata, [
                'classification_hint' => $classificationHint,
            ]),
        );
    }

    public function failed(Throwable $exception): void
    {
        DeadLetterConsumerJob::dispatch(
            payload: $this->deadLetterPayload($exception, 'ocr-extraction'),
            terminalStatus: 'extraction_failed',
        )
            ->onConnection($this->resolveQueueConnection())
            ->onQueue('queue.dead-letters');
    }

    protected function isAlreadyProcessed(Document $document, string $messageId): bool
    {
        return ProcessingEvent::query()
            ->where('tenant_id', $document->tenant_id)
            ->where('document_id', $document->id)
            ->where('message_id', $messageId)
            ->where('consumer_name', 'ocr-extraction')
            ->exists();
    }

    protected function redispatchWhileScanPending(): void
    {
        $scanWaitAttempt = $this->resolveScanWaitAttempt();
        $retryAttempts = $this->resolveRetryAttempts();

        if ($scanWaitAttempt >= $retryAttempts) {
            throw new RuntimeException('Virus scan did not finish before OCR extraction retries were exhausted.');
        }

        $payload = $this->payload;
        $metadata = $this->resolveMetadata();
        $metadata['scan_wait_attempt'] = $scanWaitAttempt + 1;
        $payload['metadata'] = $metadata;
        $payload['retry_count'] = ((int) ($payload['retry_count'] ?? 0)) + 1;

        self::dispatch($payload)
            ->delay(now()->addSeconds($this->resolveScanWaitDelaySeconds()))
            ->onConnection($this->resolveQueueConnection())
            ->onQueue('queue.ocr-extraction');
    }

    protected function resolveScanWaitDelaySeconds(): int
    {
        $delay = (int) config('processing.scan_wait_delay_seconds', 5);

        return $delay > 0 ? $delay : 5;
    }

    protected function resolveScanWaitAttempt(): int
    {
        $metadata = $this->resolveMetadata();

        return max(0, (int) ($metadata['scan_wait_attempt'] ?? 0));
    }

    protected function resolveProviderDegradedAttempt(): int
    {
        $metadata = $this->resolveMetadata();

        return max(0, (int) ($metadata['provider_degraded_attempt'] ?? 0));
    }

    protected function resolveProviderDegradedDelaySeconds(): int
    {
        $delay = (int) config('processing.provider_degraded_requeue_delay_seconds', 30);

        return $delay > 0 ? $delay : 30;
    }

    protected function redispatchWhileProviderDegraded(ProviderDegradedException $exception): void
    {
        $providerDegradedAttempt = $this->resolveProviderDegradedAttempt();
        $retryAttempts = $this->resolveRetryAttempts();

        if ($providerDegradedAttempt >= $retryAttempts) {
            throw new RuntimeException(sprintf(
                'OCR provider [%s] remained degraded after %d attempts: %s',
                $exception->provider,
                $providerDegradedAttempt,
                $exception->reason,
            ), previous: $exception);
        }

        $payload = $this->payload;
        $metadata = $this->resolveMetadata();
        $metadata['provider_degraded_attempt'] = $providerDegradedAttempt + 1;
        $metadata['provider_degraded_provider'] = $exception->provider;
        $metadata['provider_degraded_reason'] = $exception->reason;
        $payload['metadata'] = $metadata;
        $payload['retry_count'] = ((int) ($payload['retry_count'] ?? 0)) + 1;

        self::dispatch($payload)
            ->delay(now()->addSeconds($this->resolveProviderDegradedDelaySeconds()))
            ->onConnection($this->resolveQueueConnection())
            ->onQueue('queue.ocr-extraction');
    }
}

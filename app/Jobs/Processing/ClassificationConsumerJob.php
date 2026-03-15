<?php

namespace App\Jobs\Processing;

use App\Jobs\Processing\Concerns\InteractsWithProcessingPayload;
use App\Models\Document;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\ProcessingEvent;
use App\Services\DocumentStatusTransitionService;
use App\Services\Processing\ClassificationProvider;
use App\Services\Processing\ClassificationRoutingResolver;
use App\Services\Processing\ProviderDegradedException;
use App\Services\ProcessingEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ClassificationConsumerJob implements ShouldQueue
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
        ClassificationProvider $classificationProvider,
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
                consumerName: 'classification',
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

        if (in_array($statusBeforeProcessing, ['uploaded', 'scanning', 'scan_passed', 'extracting'], true)) {
            $this->redispatchWhileExtractionPending();

            return;
        }

        if (in_array($statusBeforeProcessing, ['scan_failed', 'extraction_failed', 'classification_failed'], true)) {
            return;
        }

        if ($statusBeforeProcessing === 'ready_for_review') {
            $processingEventRecorder->record(
                document: $document,
                messageId: $messageId,
                consumerName: 'classification',
                event: 'document.classification.already_ready',
                statusFrom: $statusBeforeProcessing,
                statusTo: $statusBeforeProcessing,
                traceId: $traceId,
                metadata: $metadata,
            );

            return;
        }

        if ($statusBeforeProcessing !== 'classifying') {
            return;
        }

        $extractedData = ExtractedData::query()
            ->where('document_id', $document->id)
            ->first();

        try {
            $result = $classificationProvider->classify($document, $extractedData, $this->payload);
        } catch (ProviderDegradedException $exception) {
            $this->redispatchWhileProviderDegraded($exception, $document, $classificationRoutingResolver);

            return;
        }

        $resolvedType = $classificationRoutingResolver->normalizeType(
            is_string($result['type'] ?? null) ? $result['type'] : null,
        ) ?? 'general';

        DocumentClassification::query()->updateOrCreate(
            ['document_id' => $document->id],
            [
                'tenant_id' => $document->tenant_id,
                'provider' => (string) ($result['provider'] ?? 'openai'),
                'type' => $resolvedType,
                'confidence' => isset($result['confidence'])
                    ? (float) $result['confidence']
                    : null,
                'metadata' => [
                    'provider_metadata' => is_array($result['metadata'] ?? null)
                        ? $result['metadata']
                        : [],
                ],
            ],
        );

        $document = $transitionService->transition(
            document: $document,
            toStatus: 'ready_for_review',
            consumerName: 'classification-transition',
            messageId: (string) Str::uuid(),
            metadata: [
                'pipeline' => 'classification',
                'classification_type' => $resolvedType,
            ],
        );

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'classification',
            event: 'document.classified',
            statusFrom: $statusBeforeProcessing,
            statusTo: $this->resolveDocumentStatus($document),
            traceId: $traceId,
            metadata: array_merge($metadata, [
                'classification_type' => $resolvedType,
            ]),
        );
    }

    public function failed(Throwable $exception): void
    {
        DeadLetterConsumerJob::dispatch(
            payload: $this->deadLetterPayload($exception, 'classification'),
            terminalStatus: 'classification_failed',
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
            ->where('consumer_name', 'classification')
            ->exists();
    }

    protected function redispatchWhileExtractionPending(): void
    {
        $waitAttempt = $this->resolveClassificationWaitAttempt();
        $retryAttempts = $this->resolveRetryAttempts();

        if ($waitAttempt >= $retryAttempts) {
            throw new RuntimeException('OCR extraction did not finish before classification retries were exhausted.');
        }

        $payload = $this->payload;
        $metadata = $this->resolveMetadata();
        $metadata['classification_wait_attempt'] = $waitAttempt + 1;
        $payload['metadata'] = $metadata;
        $payload['retry_count'] = ((int) ($payload['retry_count'] ?? 0)) + 1;

        self::dispatch($payload)
            ->delay(now()->addSeconds($this->resolveClassificationWaitDelaySeconds()))
            ->onConnection($this->resolveQueueConnection())
            ->onQueue('queue.classify.general');
    }

    protected function resolveClassificationWaitDelaySeconds(): int
    {
        $delay = (int) config('processing.scan_wait_delay_seconds', 5);

        return $delay > 0 ? $delay : 5;
    }

    protected function resolveClassificationWaitAttempt(): int
    {
        $metadata = $this->resolveMetadata();

        return max(0, (int) ($metadata['classification_wait_attempt'] ?? 0));
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

    protected function redispatchWhileProviderDegraded(
        ProviderDegradedException $exception,
        Document $document,
        ClassificationRoutingResolver $classificationRoutingResolver,
    ): void {
        $providerDegradedAttempt = $this->resolveProviderDegradedAttempt();
        $retryAttempts = $this->resolveRetryAttempts();

        if ($providerDegradedAttempt >= $retryAttempts) {
            throw new RuntimeException(sprintf(
                'Classification provider [%s] remained degraded after %d attempts: %s',
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

        $classificationHint = $classificationRoutingResolver->resolveTypeHint(
            is_string($metadata['classification_hint'] ?? null) ? $metadata['classification_hint'] : null,
            $document->file_name,
        );

        self::dispatch($payload)
            ->delay(now()->addSeconds($this->resolveProviderDegradedDelaySeconds()))
            ->onConnection($this->resolveQueueConnection())
            ->onQueue($classificationRoutingResolver->resolveQueueForType($classificationHint));
    }
}

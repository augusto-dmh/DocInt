<?php

namespace App\Jobs\Processing;

use App\Jobs\Processing\Concerns\InteractsWithProcessingPayload;
use App\Models\Document;
use App\Models\ProcessingEvent;
use App\Services\DocumentStatusTransitionService;
use App\Services\ProcessingEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class VirusScanConsumerJob implements ShouldQueue
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
    ): void {
        $this->assertValidMessageId();

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
                consumerName: 'virus-scan',
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

        if ($statusBeforeProcessing === 'uploaded') {
            try {
                $document = $transitionService->transition(
                    document: $document,
                    toStatus: 'scanning',
                    consumerName: 'virus-scan-transition',
                    messageId: (string) Str::uuid(),
                    metadata: ['pipeline' => 'virus-scan'],
                );
            } catch (InvalidArgumentException) {
                // A concurrent worker already advanced the document status; reload and continue.
            }
        }

        $document = $document->fresh();

        if ($document === null || ! in_array($this->resolveDocumentStatus($document), ['scanning', 'scan_passed'], true)) {
            return;
        }

        $simulateScanFailure = ($metadata['simulate_scan_failure'] ?? false) === true;

        if ($simulateScanFailure && $this->resolveDocumentStatus($document) === 'scanning') {
            $document = $transitionService->transition(
                document: $document,
                toStatus: 'scan_failed',
                consumerName: 'virus-scan-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'virus-scan', 'simulate_scan_failure' => true],
            );

            $processingEventRecorder->record(
                document: $document,
                messageId: $messageId,
                consumerName: 'virus-scan',
                event: 'document.virus_scan.failed',
                statusFrom: $statusBeforeProcessing,
                statusTo: 'scan_failed',
                traceId: $traceId,
                metadata: array_merge($metadata, ['simulate_scan_failure' => true]),
            );

            return;
        }

        if ($this->resolveDocumentStatus($document) === 'scanning') {
            $document = $transitionService->transition(
                document: $document,
                toStatus: 'scan_passed',
                consumerName: 'virus-scan-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'virus-scan'],
            );
        }

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'virus-scan',
            event: 'document.virus_scan.passed',
            statusFrom: $statusBeforeProcessing,
            statusTo: $this->resolveDocumentStatus($document),
            traceId: $traceId,
            metadata: $metadata,
        );
    }

    public function failed(Throwable $exception): void
    {
        DeadLetterConsumerJob::dispatch(
            payload: $this->deadLetterPayload($exception, 'virus-scan'),
            terminalStatus: 'scan_failed',
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
            ->where('consumer_name', 'virus-scan')
            ->exists();
    }
}

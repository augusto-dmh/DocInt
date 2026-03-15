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

class DeadLetterConsumerJob implements ShouldQueue
{
    use InteractsWithProcessingPayload, Queueable;

    public int $tries;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $terminalStatus,
    ) {
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
                consumerName: 'dead-letters',
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

        if ($transitionService->canTransition($statusBeforeProcessing, $this->terminalStatus)) {
            $document = $transitionService->transition(
                document: $document,
                toStatus: $this->terminalStatus,
                consumerName: 'dead-letters-transition',
                messageId: (string) Str::uuid(),
                metadata: array_merge($metadata, ['pipeline' => 'dead-letters']),
            );
        }

        $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => null,
            'action' => 'processing_failed',
            'metadata' => [
                'trace_id' => $traceId,
                'message_id' => $messageId,
                'failed_consumer' => $metadata['failed_consumer'] ?? null,
                'failure_reason' => $metadata['failure_reason'] ?? null,
                'terminal_status' => $this->terminalStatus,
            ],
        ]);

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'dead-letters',
            event: 'document.dead_letter.processed',
            statusFrom: $statusBeforeProcessing,
            statusTo: $this->resolveDocumentStatus($document),
            traceId: $traceId,
            metadata: array_merge($metadata, [
                'terminal_status' => $this->terminalStatus,
            ]),
        );
    }

    protected function isAlreadyProcessed(Document $document, string $messageId): bool
    {
        return ProcessingEvent::query()
            ->where('tenant_id', $document->tenant_id)
            ->where('document_id', $document->id)
            ->where('message_id', $messageId)
            ->where('consumer_name', 'dead-letters')
            ->exists();
    }
}

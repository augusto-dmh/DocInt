<?php

namespace App\Jobs\Processing;

use App\Jobs\Processing\Concerns\InteractsWithProcessingPayload;
use App\Models\Document;
use App\Models\ProcessingEvent;
use App\Services\ProcessingEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AuditLogConsumerJob implements ShouldQueue
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

    public function handle(ProcessingEventRecorder $processingEventRecorder): void
    {
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
                consumerName: 'audit-log',
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

        $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => null,
            'action' => 'processing_ingested',
            'metadata' => [
                'trace_id' => $traceId,
                'message_id' => $messageId,
                'pipeline' => 'audit-log',
            ],
        ]);

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'audit-log',
            event: 'document.audit.logged',
            statusFrom: $document->status,
            statusTo: $document->status,
            traceId: $traceId,
            metadata: $metadata,
        );
    }

    protected function isAlreadyProcessed(Document $document, string $messageId): bool
    {
        return ProcessingEvent::query()
            ->where('tenant_id', $document->tenant_id)
            ->where('document_id', $document->id)
            ->where('message_id', $messageId)
            ->where('consumer_name', 'audit-log')
            ->exists();
    }
}

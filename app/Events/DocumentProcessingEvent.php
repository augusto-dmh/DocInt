<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentProcessingEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $traceId,
        public string $tenantId,
        public int $documentId,
        public string $event,
        public CarbonImmutable $timestamp,
        public array $metadata = [],
        public int $retryCount = 0,
    ) {}

    /**
     * @return array{
     *     message_id: string,
     *     trace_id: string,
     *     tenant_id: string,
     *     document_id: int,
     *     event: string,
     *     timestamp: string,
     *     metadata: array<string, mixed>,
     *     retry_count: int
     * }
     */
    public function toPayload(): array
    {
        return [
            'message_id' => $this->messageId,
            'trace_id' => $this->traceId,
            'tenant_id' => $this->tenantId,
            'document_id' => $this->documentId,
            'event' => $this->event,
            'timestamp' => $this->timestamp->toISOString(),
            'metadata' => $this->metadata,
            'retry_count' => $this->retryCount,
        ];
    }
}

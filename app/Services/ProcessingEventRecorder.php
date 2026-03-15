<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\ProcessingEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class ProcessingEventRecorder
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        Document $document,
        string $messageId,
        string $consumerName,
        string $event,
        DocumentStatus|string|null $statusFrom = null,
        DocumentStatus|string|null $statusTo = null,
        ?string $traceId = null,
        ?array $metadata = null,
    ): ProcessingEvent {
        $uniqueKey = [
            'tenant_id' => $document->tenant_id,
            'message_id' => $messageId,
            'consumer_name' => $consumerName,
        ];

        try {
            return ProcessingEvent::query()->firstOrCreate(
                $uniqueKey,
                [
                    'document_id' => $document->id,
                    'trace_id' => $this->resolveTraceId($document, $traceId),
                    'event' => $event,
                    'status_from' => $this->resolveStatusValue($statusFrom),
                    'status_to' => $this->resolveStatusValue($statusTo),
                    'metadata' => $metadata,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            /** @var ProcessingEvent */
            return ProcessingEvent::query()->where($uniqueKey)->firstOrFail();
        }
    }

    protected function resolveTraceId(Document $document, ?string $traceId): string
    {
        $resolvedTraceId = $traceId ?? $document->processing_trace_id;

        if (is_string($resolvedTraceId) && $resolvedTraceId !== '') {
            return $resolvedTraceId;
        }

        return (string) Str::uuid();
    }

    protected function resolveStatusValue(DocumentStatus|string|null $status): ?string
    {
        if ($status instanceof DocumentStatus) {
            return $status->value;
        }

        return $status;
    }
}

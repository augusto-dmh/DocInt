<?php

namespace App\Jobs\Processing\Concerns;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\ProcessingEventRecorder;
use Illuminate\Support\Str;
use Throwable;

trait InteractsWithProcessingPayload
{
    protected function resolveRetryAttempts(): int
    {
        $configuredAttempts = (int) config('processing.retry_attempts', 3);

        return $configuredAttempts > 0 ? $configuredAttempts : 3;
    }

    protected function resolveQueueConnection(): string
    {
        $configuredConnection = config('processing.queue_connection', config('queue.default', 'sync'));

        return is_string($configuredConnection) && $configuredConnection !== ''
            ? $configuredConnection
            : 'sync';
    }

    /**
     * @return list<int>
     */
    protected function resolveRetryBackoff(): array
    {
        $configuredBackoff = config('processing.retry_backoff', [5, 15, 45]);

        if (! is_array($configuredBackoff)) {
            return [5, 15, 45];
        }

        $backoff = array_values(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $configuredBackoff),
            static fn (int $value): bool => $value > 0,
        ));

        return $backoff === [] ? [5, 15, 45] : $backoff;
    }

    protected function resolveDocumentId(): int
    {
        return (int) ($this->payload['document_id'] ?? 0);
    }

    protected function resolveTenantId(): string
    {
        return (string) ($this->payload['tenant_id'] ?? '');
    }

    protected function resolveMessageId(): string
    {
        $messageId = (string) ($this->payload['message_id'] ?? '');

        return Str::isUuid($messageId)
            ? $messageId
            : (string) Str::uuid();
    }

    /**
     * Asserts that the payload contains a valid UUID message_id before any processing begins.
     * Without a stable message_id the per-consumer idempotency check cannot function correctly,
     * so a missing or malformed value is treated as a fatal payload error and sent to the DLQ.
     */
    protected function assertValidMessageId(): void
    {
        $messageId = (string) ($this->payload['message_id'] ?? '');

        if (! Str::isUuid($messageId)) {
            throw new \RuntimeException('Pipeline payload is missing a valid message_id; idempotency cannot be guaranteed.');
        }
    }

    protected function resolveTraceId(): string
    {
        $traceId = (string) ($this->payload['trace_id'] ?? '');

        return Str::isUuid($traceId)
            ? $traceId
            : (string) Str::uuid();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveMetadata(): array
    {
        return is_array($this->payload['metadata'] ?? null)
            ? $this->payload['metadata']
            : [];
    }

    protected function resolveDocumentStatus(Document $document): string
    {
        if ($document->status instanceof DocumentStatus) {
            return $document->status->value;
        }

        return (string) $document->status;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordTenantMismatch(
        string $consumerName,
        int $documentId,
        string $tenantId,
        string $messageId,
        string $traceId,
        array $metadata,
        ProcessingEventRecorder $processingEventRecorder,
    ): void {
        $document = Document::query()->whereKey($documentId)->first();

        if ($document === null) {
            return;
        }

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: $consumerName.'-tenant-mismatch',
            event: 'document.processing.tenant_mismatch',
            statusFrom: $document->status,
            statusTo: $document->status,
            traceId: $traceId,
            metadata: array_merge($metadata, [
                'payload_tenant_id' => $tenantId,
                'resolved_tenant_id' => $document->tenant_id,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function deadLetterPayload(Throwable $exception, string $consumerName): array
    {
        $payload = $this->payload;
        $metadata = $this->resolveMetadata();
        $payload['metadata'] = array_merge($metadata, [
            'failed_consumer' => $consumerName,
            'failure_reason' => trim($exception->getMessage()) !== ''
                ? trim($exception->getMessage())
                : $exception::class,
        ]);
        $payload['retry_count'] = ((int) ($payload['retry_count'] ?? 0)) + 1;

        return $payload;
    }
}

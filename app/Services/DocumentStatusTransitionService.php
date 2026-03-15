<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Events\DocumentProcessingEvent;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentStatusTransitionService
{
    /**
     * @var array<string, list<string>>
     */
    protected const ALLOWED_TRANSITIONS = [
        'uploaded' => ['scanning'],
        'scanning' => ['scan_passed', 'scan_failed'],
        'scan_passed' => ['extracting'],
        'extracting' => ['classifying', 'extraction_failed'],
        'classifying' => ['ready_for_review', 'classification_failed'],
        'ready_for_review' => ['reviewed', 'approved'],
        'reviewed' => ['approved'],
        'scan_failed' => [],
        'extraction_failed' => [],
        'classification_failed' => [],
        'approved' => [],
    ];

    public function __construct(
        public ProcessingEventRecorder $processingEventRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(
        Document $document,
        DocumentStatus|string $toStatus,
        string $consumerName = 'pipeline-transition',
        ?string $messageId = null,
        array $metadata = [],
    ): Document {
        $resolvedToStatus = $this->resolveStatus($toStatus);

        /**
         * @var array{
         *     document: Document,
         *     message_id: string,
         *     trace_id: string,
         *     metadata: array<string, mixed>
         * } $transitionResult
         */
        $transitionResult = DB::transaction(function () use ($consumerName, $document, $messageId, $metadata, $resolvedToStatus): array {
            /** @var Document $lockedDocument */
            $lockedDocument = Document::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $this->resolveStatus($lockedDocument->status);

            if (! $this->canTransition($fromStatus, $resolvedToStatus)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid document status transition from [%s] to [%s].',
                        $fromStatus->value,
                        $resolvedToStatus->value,
                    ),
                );
            }

            $traceId = $this->ensureProcessingTraceId($lockedDocument);
            $resolvedMessageId = $messageId ?? (string) Str::uuid();
            $eventMetadata = array_merge($metadata, [
                'from_status' => $fromStatus->value,
                'to_status' => $resolvedToStatus->value,
            ]);

            $lockedDocument->update([
                'status' => $resolvedToStatus,
                'processing_trace_id' => $traceId,
            ]);

            $this->processingEventRecorder->record(
                $lockedDocument,
                $resolvedMessageId,
                $consumerName,
                'document.status.transitioned',
                $fromStatus,
                $resolvedToStatus,
                $traceId,
                $eventMetadata,
            );

            /** @var Document $freshDocument */
            $freshDocument = $lockedDocument->fresh();

            return [
                'document' => $freshDocument,
                'message_id' => $resolvedMessageId,
                'trace_id' => $traceId,
                'metadata' => $eventMetadata,
            ];
        });

        event(new DocumentProcessingEvent(
            messageId: $transitionResult['message_id'],
            traceId: $transitionResult['trace_id'],
            tenantId: $transitionResult['document']->tenant_id,
            documentId: $transitionResult['document']->id,
            event: 'document.status.transitioned',
            timestamp: now()->toImmutable(),
            metadata: $transitionResult['metadata'],
            retryCount: 0,
        ));

        return $transitionResult['document'];
    }

    public function canTransition(DocumentStatus|string $fromStatus, DocumentStatus|string $toStatus): bool
    {
        $resolvedFromStatus = $this->resolveStatus($fromStatus);
        $resolvedToStatus = $this->resolveStatus($toStatus);

        return in_array(
            $resolvedToStatus->value,
            self::ALLOWED_TRANSITIONS[$resolvedFromStatus->value] ?? [],
            true,
        );
    }

    protected function ensureProcessingTraceId(Document $document): string
    {
        if (is_string($document->processing_trace_id) && $document->processing_trace_id !== '') {
            return $document->processing_trace_id;
        }

        return (string) Str::uuid();
    }

    protected function resolveStatus(DocumentStatus|string $status): DocumentStatus
    {
        if ($status instanceof DocumentStatus) {
            return $status;
        }

        return DocumentStatus::from($status);
    }
}

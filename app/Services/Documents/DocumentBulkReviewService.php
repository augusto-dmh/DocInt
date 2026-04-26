<?php

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Events\DocumentStatusUpdated;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentStatusTransitionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentBulkReviewService
{
    public function __construct(
        protected readonly DocumentStatusTransitionService $statusTransitionService,
    ) {}

    /**
     * @param  list<int>  $documentIds
     * @return array{
     *     action: string,
     *     attempted_count: int,
     *     processed_count: int,
     *     skipped_count: int,
     *     processed_ids: list<int>,
     *     skipped: list<array{document_id: int, title: string|null, reason: string}>,
     *     message: string
     * }
     */
    public function performStatusTransition(
        array $documentIds,
        DocumentStatus $toStatus,
        string $ability,
        string $authorizationVerb,
        string $successAction,
        Authenticatable $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $selectedDocuments = $this->selectedDocumentsForBulkAction($documentIds);
        $processedIds = [];
        $skipped = [];

        foreach ($documentIds as $documentId) {
            $document = $selectedDocuments->get($documentId);

            if (! $document instanceof Document) {
                $skipped[] = $this->skippedDocument($documentId, null, 'Document is no longer available.');

                continue;
            }

            if (! $actor->can($ability, $document)) {
                $skipped[] = $this->skippedDocument(
                    $document->id,
                    $document->title,
                    sprintf('You are not allowed to %s this document.', $authorizationVerb),
                );

                continue;
            }

            try {
                $transitionedDocument = $this->statusTransitionService->transition(
                    document: $document,
                    toStatus: $toStatus,
                    consumerName: 'bulk-review',
                    messageId: (string) Str::uuid(),
                    metadata: [
                        'source' => 'documents.index.bulk',
                        'actor_user_id' => $actor->getAuthIdentifier(),
                        'bulk_action' => $successAction,
                    ],
                );
            } catch (InvalidArgumentException) {
                $skipped[] = $this->skippedDocument(
                    $document->id,
                    $document->title,
                    sprintf(
                        'Document cannot transition from [%s] to [%s].',
                        $document->status->value,
                        $toStatus->value,
                    ),
                );

                continue;
            }

            $this->writeAuditLog(
                document: $transitionedDocument,
                actor: $actor,
                action: $successAction,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                extraMetadata: ['bulk_action' => true],
            );

            $processedIds[] = $transitionedDocument->id;
        }

        return $this->buildResultPayload(
            action: $successAction,
            attemptedCount: count($documentIds),
            processedIds: $processedIds,
            skipped: $skipped,
            successVerb: $successAction,
        );
    }

    /**
     * @param  list<int>  $documentIds
     * @return array{
     *     action: string,
     *     attempted_count: int,
     *     processed_count: int,
     *     skipped_count: int,
     *     processed_ids: list<int>,
     *     skipped: list<array{document_id: int, title: string|null, reason: string}>,
     *     message: string
     * }
     */
    public function performReviewerAssignment(
        array $documentIds,
        mixed $assignedTo,
        Authenticatable $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $selectedDocuments = $this->selectedDocumentsForBulkAction($documentIds);
        $assignee = $this->resolveAssignee($assignedTo);
        $processedIds = [];
        $skipped = [];

        foreach ($documentIds as $documentId) {
            $document = $selectedDocuments->get($documentId);

            if (! $document instanceof Document) {
                $skipped[] = $this->skippedDocument($documentId, null, 'Document is no longer available.');

                continue;
            }

            if (! $actor->can('assignReviewer', $document)) {
                $skipped[] = $this->skippedDocument($document->id, $document->title, 'You are not allowed to reassign this document.');

                continue;
            }

            $document->loadMissing('assignee');

            if ($document->assigned_to === $assignee?->id) {
                $skipped[] = $this->skippedDocument($document->id, $document->title, 'Reviewer assignment is already up to date.');

                continue;
            }

            $previousAssigneeId = $document->assigned_to;
            $previousAssigneeName = $document->assignee?->name;

            $document->update([
                'assigned_to' => $assignee?->id,
            ]);

            /** @var Document $freshDocument */
            $freshDocument = $document->fresh(['assignee']);

            $this->writeAuditLog(
                document: $freshDocument,
                actor: $actor,
                action: 'reviewer_assignment_updated',
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                extraMetadata: [
                    'bulk_action' => true,
                    'previous_assignee_id' => $previousAssigneeId,
                    'previous_assignee_name' => $previousAssigneeName,
                    'assignee_id' => $assignee?->id,
                    'assignee_name' => $assignee?->name,
                ],
            );

            event(new DocumentStatusUpdated(
                document: $freshDocument,
                fromStatus: $freshDocument->status->value,
                toStatus: $freshDocument->status->value,
                traceId: $freshDocument->processing_trace_id,
            ));

            $processedIds[] = $freshDocument->id;
        }

        return $this->buildResultPayload(
            action: 'reassign',
            attemptedCount: count($documentIds),
            processedIds: $processedIds,
            skipped: $skipped,
            successVerb: 'reassigned',
        );
    }

    public function resolveAssignee(mixed $assignedTo): ?User
    {
        if (! is_numeric($assignedTo)) {
            return null;
        }

        /** @var User */
        return User::query()
            ->where('tenant_id', tenant()?->id)
            ->findOrFail((int) $assignedTo);
    }

    /**
     * @param  list<int>  $documentIds
     * @return Collection<int, Document>
     */
    protected function selectedDocumentsForBulkAction(array $documentIds): Collection
    {
        return Document::query()
            ->where('tenant_id', tenant()?->id)
            ->whereIn('id', $documentIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array{document_id: int, title: string|null, reason: string}
     */
    protected function skippedDocument(int $documentId, ?string $title, string $reason): array
    {
        return [
            'document_id' => $documentId,
            'title' => $title,
            'reason' => $reason,
        ];
    }

    /**
     * @param  list<int>  $processedIds
     * @param  list<array{document_id: int, title: string|null, reason: string}>  $skipped
     * @return array{
     *     action: string,
     *     attempted_count: int,
     *     processed_count: int,
     *     skipped_count: int,
     *     processed_ids: list<int>,
     *     skipped: list<array{document_id: int, title: string|null, reason: string}>,
     *     message: string
     * }
     */
    protected function buildResultPayload(
        string $action,
        int $attemptedCount,
        array $processedIds,
        array $skipped,
        string $successVerb,
    ): array {
        $processedCount = count($processedIds);
        $skippedCount = count($skipped);

        $message = $processedCount > 0
            ? sprintf(
                '%s %d %s%s',
                ucfirst($successVerb),
                $processedCount,
                Str::plural('document', $processedCount),
                $skippedCount > 0 ? sprintf('. Skipped %d.', $skippedCount) : '.',
            )
            : sprintf('No documents were %s.', $successVerb);

        return [
            'action' => $action,
            'attempted_count' => $attemptedCount,
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
            'processed_ids' => $processedIds,
            'skipped' => $skipped,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $extraMetadata
     */
    protected function writeAuditLog(
        Document $document,
        Authenticatable $actor,
        string $action,
        ?string $ipAddress,
        ?string $userAgent,
        array $extraMetadata = [],
    ): void {
        $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $actor->getAuthIdentifier(),
            'action' => $action,
            'metadata' => array_merge([
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ], $extraMetadata),
        ]);
    }
}

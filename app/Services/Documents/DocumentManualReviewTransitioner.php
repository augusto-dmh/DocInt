<?php

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentStatusTransitionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DocumentManualReviewTransitioner
{
    public function __construct(
        protected readonly DocumentStatusTransitionService $statusTransitionService,
    ) {}

    /**
     * Transition the document for a manual-review action and persist an audit
     * log of the action. Translates a domain InvalidArgumentException raised
     * by the status state-machine into a ValidationException keyed by 'status'.
     */
    public function transition(
        Document $document,
        DocumentStatus $toStatus,
        Authenticatable $actor,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): Document {
        try {
            $transitionedDocument = $this->statusTransitionService->transition(
                document: $document,
                toStatus: $toStatus,
                consumerName: 'manual-review',
                messageId: (string) Str::uuid(),
                metadata: [
                    'source' => 'documents.show',
                    'actor_user_id' => $actor->getAuthIdentifier(),
                ],
            );
        } catch (InvalidArgumentException) {
            $currentStatus = $document->fresh()?->status;
            $fromStatus = $currentStatus instanceof DocumentStatus
                ? $currentStatus->value
                : $document->status->value;

            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Document cannot transition from [%s] to [%s].',
                    $fromStatus,
                    $toStatus->value,
                ),
            ]);
        }

        $transitionedDocument->auditLogs()->create([
            'tenant_id' => $transitionedDocument->tenant_id,
            'user_id' => $actor->getAuthIdentifier(),
            'action' => $toStatus->value,
            'metadata' => [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ],
        ]);

        return $transitionedDocument;
    }
}

<?php

namespace App\Events;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Support\DocumentReviewWorkspacePresenter;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class DocumentCommentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public readonly string $tenantId;

    public readonly int $documentId;

    public readonly string $action;

    /**
     * @var array{id: int, parent_id: int|null, body: string, created_at: string, updated_at: string, user: array{id: int, name: string}|null}|null
     */
    public readonly ?array $comment;

    public readonly ?int $commentId;

    /**
     * @var array{id: int, action: string, details: string|null, created_at: string, user: array{id: int, name: string}|null, ip_address: string|null}|null
     */
    public readonly ?array $activity;

    public readonly string $occurredAt;

    public function __construct(
        Document $document,
        string $action,
        ?DocumentComment $comment = null,
        ?AuditLog $activity = null,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->tenantId = $document->tenant_id;
        $this->documentId = $document->id;
        $this->action = $action;
        $this->comment = $comment ? DocumentReviewWorkspacePresenter::comment($comment) : null;
        $this->commentId = $comment?->id;
        $this->activity = $activity ? DocumentReviewWorkspacePresenter::auditLog($activity) : null;
        $this->occurredAt = ($occurredAt ?? now()->toImmutable())->toISOString();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("documents.{$this->documentId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document.comment.updated';
    }

    /**
     * @return array{
     *     tenant_id: string,
     *     document_id: int,
     *     action: string,
     *     comment: array{id: int, parent_id: int|null, body: string, created_at: string, updated_at: string, user: array{id: int, name: string}|null}|null,
     *     comment_id: int|null,
     *     activity: array{id: int, action: string, details: string|null, created_at: string, user: array{id: int, name: string}|null, ip_address: string|null}|null,
     *     occurred_at: string
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'document_id' => $this->documentId,
            'action' => $this->action,
            'comment' => $this->comment,
            'comment_id' => $this->commentId,
            'activity' => $this->activity,
            'occurred_at' => $this->occurredAt,
        ];
    }
}

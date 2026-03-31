<?php

namespace App\Events;

use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class DocumentStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public readonly string $tenantId;

    public readonly int $documentId;

    public readonly ?string $fromStatus;

    public readonly string $toStatus;

    public readonly ?string $traceId;

    public readonly string $occurredAt;

    /**
     * @var array{title: string, matter_title: string|null}|null
     */
    public readonly ?array $document;

    public function __construct(
        Document $document,
        ?string $fromStatus,
        string $toStatus,
        ?string $traceId = null,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->tenantId = $document->tenant_id;
        $this->documentId = $document->id;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->traceId = $traceId;
        $this->occurredAt = ($occurredAt ?? now()->toImmutable())->toISOString();
        $this->document = $this->documentSnapshot($document);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenants.{$this->tenantId}.documents"),
            new PrivateChannel("documents.{$this->documentId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document.status.updated';
    }

    /**
     * @return array{
     *     tenant_id: string,
     *     document_id: int,
     *     from_status: string|null,
     *     to_status: string,
     *     trace_id: string|null,
     *     occurred_at: string,
     *     document: array{title: string, matter_title: string|null}|null
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'document_id' => $this->documentId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'trace_id' => $this->traceId,
            'occurred_at' => $this->occurredAt,
            'document' => $this->document,
        ];
    }

    /**
     * @return array{title: string, matter_title: string|null}|null
     */
    protected function documentSnapshot(Document $document): ?array
    {
        $document->loadMissing('matter:id,title');

        if (! is_string($document->title) || $document->title === '') {
            return null;
        }

        return [
            'title' => $document->title,
            'matter_title' => $document->matter?->title,
        ];
    }
}

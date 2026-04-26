<?php

namespace App\Services\Documents;

use App\Events\DocumentStatusUpdated;
use App\Models\Document;
use App\Models\User;

class DocumentReviewerAssignmentService
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public function availableReviewersForAssignment(Document $document): array
    {
        return $this->availableReviewersForTenant($document->tenant_id);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function availableReviewersForTenant(?string $tenantId): array
    {
        return $this->withPermissionTeamContext($tenantId, function () use ($tenantId): array {
            return User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get()
                ->filter(fn (User $user): bool => $user->hasRole('associate'))
                ->values()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                ])
                ->all();
        });
    }

    public function resolveAssignee(mixed $assignedTo, Document $document): ?User
    {
        if (! is_numeric($assignedTo)) {
            return null;
        }

        /** @var User */
        return User::query()
            ->where('tenant_id', $document->tenant_id)
            ->findOrFail((int) $assignedTo);
    }

    /**
     * @return array{previous_assignee_id: int|null, previous_assignee_name: string|null, assignee_id: int|null, assignee_name: string|null, document: Document, changed: bool}
     */
    public function assign(Document $document, ?User $assignee): array
    {
        $document->loadMissing('assignee');

        $previousAssigneeId = $document->assigned_to;
        $previousAssigneeName = $document->assignee?->name;

        if ($previousAssigneeId === $assignee?->id) {
            return [
                'previous_assignee_id' => $previousAssigneeId,
                'previous_assignee_name' => $previousAssigneeName,
                'assignee_id' => $assignee?->id,
                'assignee_name' => $assignee?->name,
                'document' => $document,
                'changed' => false,
            ];
        }

        $document->update([
            'assigned_to' => $assignee?->id,
        ]);

        /** @var Document $freshDocument */
        $freshDocument = $document->fresh(['assignee']);

        event(new DocumentStatusUpdated(
            document: $freshDocument,
            fromStatus: $freshDocument->status->value,
            toStatus: $freshDocument->status->value,
            traceId: $freshDocument->processing_trace_id,
        ));

        return [
            'previous_assignee_id' => $previousAssigneeId,
            'previous_assignee_name' => $previousAssigneeName,
            'assignee_id' => $assignee?->id,
            'assignee_name' => $assignee?->name,
            'document' => $freshDocument,
            'changed' => true,
        ];
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function withPermissionTeamContext(?string $tenantId, callable $callback): mixed
    {
        if (! function_exists('getPermissionsTeamId') || ! function_exists('setPermissionsTeamId')) {
            return $callback();
        }

        $originalTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($originalTeamId);
        }
    }
}

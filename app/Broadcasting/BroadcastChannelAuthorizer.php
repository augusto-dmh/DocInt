<?php

namespace App\Broadcasting;

use App\Models\Document;
use App\Models\User;

class BroadcastChannelAuthorizer
{
    public function canAccessTenantDocumentsChannel(User $user, string $tenantId): bool
    {
        if (! $user->hasSuperAdminRole()) {
            return is_string($user->tenant_id)
                && $user->tenant_id !== ''
                && $user->tenant_id === $tenantId;
        }

        $selectedTenantId = $this->selectedTenantId();

        return is_string($selectedTenantId)
            && $selectedTenantId !== ''
            && $selectedTenantId === $tenantId;
    }

    public function canAccessDocumentChannel(User $user, int $documentId): bool
    {
        $document = Document::query()
            ->select(['id', 'tenant_id'])
            ->find($documentId);

        if (! $document instanceof Document) {
            return false;
        }

        return $this->canAccessTenantDocumentsChannel($user, $document->tenant_id);
    }

    protected function selectedTenantId(): ?string
    {
        $sessionKey = config('tenancy.tenant_context.session_key');
        $resolvedSessionKey = is_string($sessionKey) && $sessionKey !== ''
            ? $sessionKey
            : 'active_tenant_id';
        $selectedTenantId = session($resolvedSessionKey);

        if (! is_string($selectedTenantId) || $selectedTenantId === '') {
            return null;
        }

        return $selectedTenantId;
    }
}

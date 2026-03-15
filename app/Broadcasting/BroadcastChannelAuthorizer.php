<?php

namespace App\Broadcasting;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;

class BroadcastChannelAuthorizer
{
    public function canAccessTenantDocumentsChannel(User $user, string $tenantId): bool
    {
        $activeTenant = tenant();

        if (! $activeTenant instanceof Tenant) {
            return false;
        }

        return $activeTenant->id === $tenantId;
    }

    public function canAccessDocumentChannel(User $user, int $documentId): bool
    {
        $activeTenant = tenant();

        if (! $activeTenant instanceof Tenant) {
            return false;
        }

        return Document::query()
            ->whereKey($documentId)
            ->where('tenant_id', $activeTenant->id)
            ->exists();
    }
}

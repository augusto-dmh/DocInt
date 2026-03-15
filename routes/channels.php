<?php

use App\Broadcasting\BroadcastChannelAuthorizer;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenants.{tenantId}.documents', function (User $user, string $tenantId): bool {
    return app(BroadcastChannelAuthorizer::class)
        ->canAccessTenantDocumentsChannel($user, $tenantId);
});

Broadcast::channel('documents.{documentId}', function (User $user, int $documentId): bool {
    return app(BroadcastChannelAuthorizer::class)
        ->canAccessDocumentChannel($user, $documentId);
});

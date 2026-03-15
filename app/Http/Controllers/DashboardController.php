<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $realtimeTenantId = $this->resolveRealtimeTenantId($request, $request->user());

        return Inertia::render('Dashboard', [
            'realtimeTenantId' => $realtimeTenantId,
            'stats' => fn (): array => $this->dashboardStats($realtimeTenantId),
            'recentDocuments' => fn (): array => $this->recentDocuments($realtimeTenantId),
        ]);
    }

    protected function resolveRealtimeTenantId(Request $request, mixed $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        if (! $user->hasSuperAdminRole()) {
            return $user->tenant_id;
        }

        $selectedTenantId = $request->session()->get(
            config('tenancy.tenant_context.session_key', 'active_tenant_id')
        );

        if (! is_string($selectedTenantId) || $selectedTenantId === '') {
            return null;
        }

        if (! Tenant::query()->whereKey($selectedTenantId)->exists()) {
            return null;
        }

        return $selectedTenantId;
    }

    /**
     * @return array{processed_today: int, pending_review: int, failed: int}
     */
    protected function dashboardStats(?string $tenantId): array
    {
        if ($tenantId === null) {
            return [
                'processed_today' => 0,
                'pending_review' => 0,
                'failed' => 0,
            ];
        }

        return [
            'processed_today' => Document::query()
                ->where('tenant_id', $tenantId)
                ->where('status', DocumentStatus::Approved)
                ->whereDate('updated_at', now()->toDateString())
                ->count(),
            'pending_review' => Document::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [DocumentStatus::ReadyForReview, DocumentStatus::Reviewed])
                ->count(),
            'failed' => Document::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [
                    DocumentStatus::ScanFailed,
                    DocumentStatus::ExtractionFailed,
                    DocumentStatus::ClassificationFailed,
                ])
                ->count(),
        ];
    }

    /**
     * @return list<array{id: int, title: string, status: string, matter_title: string|null, updated_at: string}>
     */
    protected function recentDocuments(?string $tenantId): array
    {
        if ($tenantId === null) {
            return [];
        }

        return Document::query()
            ->where('tenant_id', $tenantId)
            ->with('matter:id,title')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (Document $document): array {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'status' => $document->status->value,
                    'matter_title' => $document->matter?->title,
                    'updated_at' => $document->updated_at->toISOString(),
                ];
            })
            ->values()
            ->all();
    }
}

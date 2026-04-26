<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\Documents\BulkAssignDocumentReviewerRequest;
use App\Http\Requests\Documents\BulkReviewDocumentsRequest;
use App\Models\User;
use App\Services\Documents\DocumentBulkReviewService;
use Illuminate\Http\JsonResponse;

class BulkDocumentReviewController extends Controller
{
    public function __construct(
        public readonly DocumentBulkReviewService $documentBulkReviewService,
    ) {}

    public function approve(BulkReviewDocumentsRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->documentBulkReviewService->performStatusTransition(
            documentIds: $request->validated('document_ids'),
            toStatus: DocumentStatus::Approved,
            ability: 'approve',
            authorizationVerb: 'approve',
            successAction: 'approved',
            actor: $actor,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));
    }

    public function reject(BulkReviewDocumentsRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->documentBulkReviewService->performStatusTransition(
            documentIds: $request->validated('document_ids'),
            toStatus: DocumentStatus::Rejected,
            ability: 'review',
            authorizationVerb: 'reject',
            successAction: 'rejected',
            actor: $actor,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));
    }

    public function assignReviewer(BulkAssignDocumentReviewerRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->documentBulkReviewService->performReviewerAssignment(
            documentIds: $request->validated('document_ids'),
            assignedTo: $request->validated('assigned_to'),
            actor: $actor,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));
    }
}

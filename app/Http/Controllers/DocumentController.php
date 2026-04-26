<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Events\DocumentStatusUpdated;
use App\Http\Requests\Documents\AssignDocumentReviewerRequest;
use App\Http\Requests\Documents\BulkAssignDocumentReviewerRequest;
use App\Http\Requests\Documents\BulkReviewDocumentsRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\DocumentClassification;
use App\Models\DocumentComment;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\User;
use App\Services\Documents\DocumentReviewerAssignmentService;
use App\Services\DocumentStatusTransitionService;
use App\Services\DocumentUploadService;
use App\Support\DocumentExperienceGuardrails;
use App\Support\DocumentReviewWorkspacePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        public DocumentUploadService $documentUploadService,
        public DocumentStatusTransitionService $documentStatusTransitionService,
        public DocumentReviewerAssignmentService $documentReviewerAssignmentService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Document::class);

        return Inertia::render('documents/Index', [
            'documents' => fn () => Document::query()
                ->with(['matter', 'uploader'])
                ->latest()
                ->paginate(15),
            'bulkReview' => fn (): array => [
                'availableReviewers' => $request->user()?->can('manage users')
                    ? $this->documentReviewerAssignmentService->availableReviewersForTenant(tenant()?->id)
                    : [],
                'permissions' => [
                    'canBulkApprove' => $request->user()?->can('approve documents') === true,
                    'canBulkReject' => $request->user()?->can('review documents') === true,
                    'canBulkReassign' => $request->user()?->can('manage users') === true,
                ],
            ],
            'documentExperience' => fn () => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function create(Matter $matter): Response
    {
        $this->authorize('create', Document::class);

        return Inertia::render('documents/Create', [
            'matter' => $matter->load('client'),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function store(StoreDocumentRequest $request, Matter $matter): RedirectResponse
    {
        $this->authorize('create', Document::class);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        /** @var User $user */
        $user = $request->user();

        $document = $this->documentUploadService->upload(
            $file,
            $matter,
            $user,
            $request->validated('title'),
        );

        return to_route('documents.show', $document);
    }

    public function preview(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(DocumentReviewWorkspacePresenter::supportsInlinePreview($document), 404);

        $disk = Storage::disk('s3');

        abort_unless(
            $document->file_path !== '' && $disk->exists($document->file_path),
            404,
        );

        $stream = $disk->readStream($document->file_path);

        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $document->file_name,
                Str::ascii($document->file_name),
            ),
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(Request $request, Document $document): Response
    {
        $this->authorize('view', $document);

        if (! $this->isRealtimeRefresh($request)) {
            $this->logDocumentAction($document, $request, 'viewed');
        }

        return Inertia::render('documents/Show', [
            'document' => fn () => $document->load(['matter.client', 'uploader', 'assignee']),
            'recentActivity' => fn () => $document->auditLogs()
                ->with('user:id,name')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (AuditLog $auditLog): array => DocumentReviewWorkspacePresenter::auditLog($auditLog))
                ->values(),
            'processingActivity' => fn () => $document->processingEvents()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (ProcessingEvent $processingEvent): array => $this->formatProcessingEvent($processingEvent))
                ->values(),
            'reviewWorkspace' => fn (): array => [
                'preview' => DocumentReviewWorkspacePresenter::preview($document),
                'annotations' => $document->annotations()
                    ->with('user:id,name')
                    ->latest()
                    ->get()
                    ->map(fn (DocumentAnnotation $annotation): array => DocumentReviewWorkspacePresenter::annotation(
                        $annotation,
                        $request->user()?->id,
                    ))
                    ->values(),
                'comments' => $document->comments()
                    ->with('user:id,name')
                    ->oldest()
                    ->get()
                    ->map(fn (DocumentComment $comment): array => DocumentReviewWorkspacePresenter::comment($comment))
                    ->values(),
                'availableReviewers' => $request->user()?->can('assignReviewer', $document) === true
                    ? $this->documentReviewerAssignmentService->availableReviewersForAssignment($document)
                    : [],
                'permissions' => [
                    'canAnnotate' => $request->user()?->can('annotate', $document) === true
                        && DocumentReviewWorkspacePresenter::supportsInlinePreview($document),
                    'canAssignReviewer' => $request->user()?->can('assignReviewer', $document) === true,
                    'canComment' => $request->user()?->can('comment', $document) === true,
                    'canModerateComments' => $request->user()?->can('moderateComments', $document) === true,
                ],
            ],
            'extractedData' => fn () => $this->formatExtractedData(
                $document->extractedData()->first(),
            ),
            'classification' => fn () => $this->formatClassification(
                $document->classification()->first(),
            ),
            'documentExperience' => fn () => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function edit(Document $document): Response
    {
        $this->authorize('update', $document);

        return Inertia::render('documents/Edit', [
            'document' => $document->load(['matter.client', 'uploader']),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function review(Request $request, Document $document): RedirectResponse
    {
        return $this->transitionForManualReview(
            request: $request,
            document: $document,
            toStatus: DocumentStatus::Reviewed,
            ability: 'review',
        );
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        return $this->transitionForManualReview(
            request: $request,
            document: $document,
            toStatus: DocumentStatus::Approved,
            ability: 'approve',
        );
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        return $this->transitionForManualReview(
            request: $request,
            document: $document,
            toStatus: DocumentStatus::Rejected,
            ability: 'review',
        );
    }

    public function assignReviewer(
        AssignDocumentReviewerRequest $request,
        Document $document,
    ): RedirectResponse {
        $this->authorize('assignReviewer', $document);

        $assignee = $this->documentReviewerAssignmentService->resolveAssignee(
            $request->validated('assigned_to'),
            $document,
        );

        $result = $this->documentReviewerAssignmentService->assign($document, $assignee);

        if (! $result['changed']) {
            return to_route('documents.show', $document);
        }

        $this->logDocumentAction($result['document'], $request, 'reviewer_assignment_updated', [
            'previous_assignee_id' => $result['previous_assignee_id'],
            'previous_assignee_name' => $result['previous_assignee_name'],
            'assignee_id' => $result['assignee_id'],
            'assignee_name' => $result['assignee_name'],
        ]);

        return to_route('documents.show', $result['document']);
    }

    public function bulkApprove(BulkReviewDocumentsRequest $request): JsonResponse
    {
        return $this->performBulkStatusTransition(
            request: $request,
            toStatus: DocumentStatus::Approved,
            ability: 'approve',
            authorizationVerb: 'approve',
            successAction: 'approved',
        );
    }

    public function bulkReject(BulkReviewDocumentsRequest $request): JsonResponse
    {
        return $this->performBulkStatusTransition(
            request: $request,
            toStatus: DocumentStatus::Rejected,
            ability: 'review',
            authorizationVerb: 'reject',
            successAction: 'rejected',
        );
    }

    public function bulkAssignReviewer(BulkAssignDocumentReviewerRequest $request): JsonResponse
    {
        /** @var list<int> $documentIds */
        $documentIds = $request->validated('document_ids');
        $selectedDocuments = $this->selectedDocumentsForBulkAction($documentIds);
        $assignee = $this->resolveBulkReviewerAssignee($request->validated('assigned_to'));
        $processedIds = [];
        $skipped = [];

        foreach ($documentIds as $documentId) {
            $document = $selectedDocuments->get($documentId);

            if (! $document instanceof Document) {
                $skipped[] = $this->bulkSkippedDocument($documentId, null, 'Document is no longer available.');

                continue;
            }

            if (! $request->user()?->can('assignReviewer', $document)) {
                $skipped[] = $this->bulkSkippedDocument($document->id, $document->title, 'You are not allowed to reassign this document.');

                continue;
            }

            $document->loadMissing('assignee');

            if ($document->assigned_to === $assignee?->id) {
                $skipped[] = $this->bulkSkippedDocument($document->id, $document->title, 'Reviewer assignment is already up to date.');

                continue;
            }

            $previousAssigneeId = $document->assigned_to;
            $previousAssigneeName = $document->assignee?->name;

            $document->update([
                'assigned_to' => $assignee?->id,
            ]);

            /** @var Document $freshDocument */
            $freshDocument = $document->fresh(['assignee']);

            $this->logDocumentAction($freshDocument, $request, 'reviewer_assignment_updated', [
                'bulk_action' => true,
                'previous_assignee_id' => $previousAssigneeId,
                'previous_assignee_name' => $previousAssigneeName,
                'assignee_id' => $assignee?->id,
                'assignee_name' => $assignee?->name,
            ]);

            event(new DocumentStatusUpdated(
                document: $freshDocument,
                fromStatus: $freshDocument->status->value,
                toStatus: $freshDocument->status->value,
                traceId: $freshDocument->processing_trace_id,
            ));

            $processedIds[] = $freshDocument->id;
        }

        return response()->json($this->bulkActionResultPayload(
            action: 'reassign',
            attemptedCount: count($documentIds),
            processedIds: $processedIds,
            skipped: $skipped,
            successVerb: 'reassigned',
        ));
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $originalTitle = $document->title;
        $document->update($request->validated());
        $this->logDocumentAction($document, $request, 'updated', [
            'changes' => [
                'title' => [
                    'from' => $originalTitle,
                    'to' => $document->title,
                ],
            ],
        ]);

        return to_route('documents.show', $document);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        /** @var User $user */
        $user = $request->user();

        $this->documentUploadService->delete($document, $user);

        return to_route('documents.index');
    }

    public function download(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('view', $document);

        $this->logDocumentAction($document, $request, 'downloaded');

        return redirect()->away($this->documentUploadService->generatePresignedUrl($document));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function logDocumentAction(Document $document, Request $request, string $action, array $metadata = []): void
    {
        $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'metadata' => array_merge([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], $metadata),
        ]);
    }

    protected function isRealtimeRefresh(Request $request): bool
    {
        return $request->header('X-Inertia-Partial-Data') !== null;
    }

    protected function resolveBulkReviewerAssignee(mixed $assignedTo): ?User
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
     * @return array{id: int, consumer_name: string, status_from: string|null, status_to: string|null, event: string, created_at: string}
     */
    protected function formatProcessingEvent(ProcessingEvent $processingEvent): array
    {
        return [
            'id' => $processingEvent->id,
            'consumer_name' => $processingEvent->consumer_name,
            'status_from' => $processingEvent->status_from,
            'status_to' => $processingEvent->status_to,
            'event' => $processingEvent->event,
            'created_at' => $processingEvent->created_at->toISOString(),
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     extracted_text: string|null,
     *     payload: array<mixed>|null,
     *     metadata: array<mixed>|null,
     *     created_at: string,
     *     updated_at: string
     * }|null
     */
    protected function formatExtractedData(?ExtractedData $extractedData): ?array
    {
        if ($extractedData === null) {
            return null;
        }

        return [
            'provider' => $extractedData->provider,
            'extracted_text' => $extractedData->extracted_text,
            'payload' => is_array($extractedData->payload) ? $extractedData->payload : null,
            'metadata' => is_array($extractedData->metadata) ? $extractedData->metadata : null,
            'created_at' => $extractedData->created_at->toISOString(),
            'updated_at' => $extractedData->updated_at->toISOString(),
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     type: string,
     *     confidence: float|null,
     *     metadata: array<mixed>|null,
     *     created_at: string,
     *     updated_at: string
     * }|null
     */
    protected function formatClassification(?DocumentClassification $classification): ?array
    {
        if ($classification === null) {
            return null;
        }

        return [
            'provider' => $classification->provider,
            'type' => $classification->type,
            'confidence' => is_numeric($classification->confidence) ? (float) $classification->confidence : null,
            'metadata' => is_array($classification->metadata) ? $classification->metadata : null,
            'created_at' => $classification->created_at->toISOString(),
            'updated_at' => $classification->updated_at->toISOString(),
        ];
    }

    protected function transitionForManualReview(
        Request $request,
        Document $document,
        DocumentStatus $toStatus,
        string $ability,
    ): RedirectResponse {
        $this->authorize($ability, $document);

        try {
            $transitionedDocument = $this->documentStatusTransitionService->transition(
                document: $document,
                toStatus: $toStatus,
                consumerName: 'manual-review',
                messageId: (string) Str::uuid(),
                metadata: [
                    'source' => 'documents.show',
                    'actor_user_id' => $request->user()?->id,
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

        $this->logDocumentAction($transitionedDocument, $request, $toStatus->value);

        return to_route('documents.show', $transitionedDocument);
    }

    protected function performBulkStatusTransition(
        BulkReviewDocumentsRequest $request,
        DocumentStatus $toStatus,
        string $ability,
        string $authorizationVerb,
        string $successAction,
    ): JsonResponse {
        /** @var list<int> $documentIds */
        $documentIds = $request->validated('document_ids');
        $selectedDocuments = $this->selectedDocumentsForBulkAction($documentIds);
        $processedIds = [];
        $skipped = [];

        foreach ($documentIds as $documentId) {
            $document = $selectedDocuments->get($documentId);

            if (! $document instanceof Document) {
                $skipped[] = $this->bulkSkippedDocument($documentId, null, 'Document is no longer available.');

                continue;
            }

            if (! $request->user()?->can($ability, $document)) {
                $skipped[] = $this->bulkSkippedDocument(
                    $document->id,
                    $document->title,
                    sprintf('You are not allowed to %s this document.', $authorizationVerb),
                );

                continue;
            }

            try {
                $transitionedDocument = $this->documentStatusTransitionService->transition(
                    document: $document,
                    toStatus: $toStatus,
                    consumerName: 'bulk-review',
                    messageId: (string) Str::uuid(),
                    metadata: [
                        'source' => 'documents.index.bulk',
                        'actor_user_id' => $request->user()?->id,
                        'bulk_action' => $successAction,
                    ],
                );
            } catch (InvalidArgumentException) {
                $skipped[] = $this->bulkSkippedDocument(
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

            $this->logDocumentAction($transitionedDocument, $request, $successAction, [
                'bulk_action' => true,
            ]);

            $processedIds[] = $transitionedDocument->id;
        }

        return response()->json($this->bulkActionResultPayload(
            action: $successAction,
            attemptedCount: count($documentIds),
            processedIds: $processedIds,
            skipped: $skipped,
            successVerb: $successAction,
        ));
    }

    /**
     * @param  list<int>  $documentIds
     * @return \Illuminate\Support\Collection<int, Document>
     */
    protected function selectedDocumentsForBulkAction(array $documentIds): \Illuminate\Support\Collection
    {
        return Document::query()
            ->where('tenant_id', tenant()?->id)
            ->whereIn('id', $documentIds)
            ->get()
            ->keyBy('id');
    }

    /**
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
    protected function bulkActionResultPayload(
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
     * @return array{document_id: int, title: string|null, reason: string}
     */
    protected function bulkSkippedDocument(int $documentId, ?string $title, string $reason): array
    {
        return [
            'document_id' => $documentId,
            'title' => $title,
            'reason' => $reason,
        ];
    }

}

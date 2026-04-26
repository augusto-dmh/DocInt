<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Requests\Documents\AssignDocumentReviewerRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\DocumentComment;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\User;
use App\Services\Documents\DocumentManualReviewTransitioner;
use App\Services\Documents\DocumentReviewerAssignmentService;
use App\Services\Documents\DocumentShowPresenter;
use App\Services\DocumentUploadService;
use App\Support\DocumentExperienceGuardrails;
use App\Support\DocumentReviewWorkspacePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        public DocumentUploadService $documentUploadService,
        public DocumentReviewerAssignmentService $documentReviewerAssignmentService,
        public DocumentManualReviewTransitioner $documentManualReviewTransitioner,
        public DocumentShowPresenter $documentShowPresenter,
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
                ->map(fn (ProcessingEvent $processingEvent): array => $this->documentShowPresenter->formatProcessingEvent($processingEvent))
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
            'extractedData' => fn () => $this->documentShowPresenter->formatExtractedData(
                $document->extractedData()->first(),
            ),
            'classification' => fn () => $this->documentShowPresenter->formatClassification(
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
        return $this->runManualReviewTransition($request, $document, DocumentStatus::Reviewed, 'review');
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        return $this->runManualReviewTransition($request, $document, DocumentStatus::Approved, 'approve');
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        return $this->runManualReviewTransition($request, $document, DocumentStatus::Rejected, 'review');
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

    protected function runManualReviewTransition(
        Request $request,
        Document $document,
        DocumentStatus $toStatus,
        string $ability,
    ): RedirectResponse {
        $this->authorize($ability, $document);

        /** @var User $actor */
        $actor = $request->user();

        $transitionedDocument = $this->documentManualReviewTransitioner->transition(
            document: $document,
            toStatus: $toStatus,
            actor: $actor,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return to_route('documents.show', $transitionedDocument);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\User;
use App\Services\DocumentUploadService;
use App\Support\DocumentExperienceGuardrails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(public DocumentUploadService $documentUploadService) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Document::class);

        return Inertia::render('documents/Index', [
            'documents' => fn () => Document::query()
                ->with(['matter', 'uploader'])
                ->latest()
                ->paginate(15),
            'documentExperience' => fn () => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function create(Matter $matter): Response
    {
        $matter = $this->ensureCurrentTenantMatter($matter);
        $this->authorize('create', Document::class);

        return Inertia::render('documents/Create', [
            'matter' => $matter->load('client'),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function store(StoreDocumentRequest $request, Matter $matter): RedirectResponse
    {
        $matter = $this->ensureCurrentTenantMatter($matter);
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

    public function show(Request $request, Document $document): Response
    {
        $document = $this->ensureCurrentTenantDocument($document);
        $this->authorize('view', $document);

        if (! $this->isRealtimeRefresh($request)) {
            $this->logDocumentAction($document, $request, 'viewed');
        }

        return Inertia::render('documents/Show', [
            'document' => fn () => $document->load(['matter.client', 'uploader']),
            'recentActivity' => fn () => $document->auditLogs()
                ->with('user:id,name')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (AuditLog $auditLog): array => $this->formatAuditLog($auditLog))
                ->values(),
            'processingActivity' => fn () => $document->processingEvents()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (ProcessingEvent $processingEvent): array => $this->formatProcessingEvent($processingEvent))
                ->values(),
            'documentExperience' => fn () => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function edit(Document $document): Response
    {
        $document = $this->ensureCurrentTenantDocument($document);
        $this->authorize('update', $document);

        return Inertia::render('documents/Edit', [
            'document' => $document->load(['matter.client', 'uploader']),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $document = $this->ensureCurrentTenantDocument($document);
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
        $document = $this->ensureCurrentTenantDocument($document);
        $this->authorize('delete', $document);

        /** @var User $user */
        $user = $request->user();

        $this->documentUploadService->delete($document, $user);

        return to_route('documents.index');
    }

    public function download(Request $request, Document $document): RedirectResponse
    {
        $document = $this->ensureCurrentTenantDocument($document);
        $this->authorize('view', $document);

        $this->logDocumentAction($document, $request, 'downloaded');

        return redirect()->away($this->documentUploadService->generatePresignedUrl($document));
    }

    /**
     * @param array<string, mixed> $metadata
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

    protected function ensureCurrentTenantMatter(Matter $matter): Matter
    {
        abort_unless($matter->tenant_id === tenant()?->id, 404);

        return $matter;
    }

    protected function ensureCurrentTenantDocument(Document $document): Document
    {
        abort_unless($document->tenant_id === tenant()?->id, 404);

        return $document;
    }

    protected function isRealtimeRefresh(Request $request): bool
    {
        return $request->header('X-Inertia-Partial-Data') !== null;
    }

    /**
     * @return array{id: int, action: string, created_at: string, user: array{id: int, name: string}|null, ip_address: string|null}
     */
    protected function formatAuditLog(AuditLog $auditLog): array
    {
        $metadata = is_array($auditLog->metadata) ? $auditLog->metadata : [];

        return [
            'id' => $auditLog->id,
            'action' => $auditLog->action,
            'created_at' => $auditLog->created_at->toISOString(),
            'user' => $auditLog->user
                ? [
                    'id' => $auditLog->user->id,
                    'name' => $auditLog->user->name,
                ]
                : null,
            'ip_address' => is_string($metadata['ip_address'] ?? null)
                ? $metadata['ip_address']
                : null,
        ];
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
}

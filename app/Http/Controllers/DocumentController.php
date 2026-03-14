<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use App\Services\DocumentUploadService;
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
            'documents' => Document::query()
                ->with(['matter', 'uploader'])
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(Matter $matter): Response
    {
        $matter = $this->ensureCurrentTenantMatter($matter);
        $this->authorize('create', Document::class);

        return Inertia::render('documents/Create', [
            'matter' => $matter->load('client'),
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

        $this->logDocumentAction($document, $request, 'viewed');

        return Inertia::render('documents/Show', [
            'document' => $document->load(['matter.client', 'uploader']),
            'recentActivity' => $document->auditLogs()
                ->with('user:id,name')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (AuditLog $auditLog): array => $this->formatAuditLog($auditLog))
                ->values(),
        ]);
    }

    public function edit(Document $document): Response
    {
        $document = $this->ensureCurrentTenantDocument($document);
        $this->authorize('update', $document);

        return Inertia::render('documents/Edit', [
            'document' => $document->load(['matter.client', 'uploader']),
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
}

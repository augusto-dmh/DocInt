<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\User;
use App\Services\DocumentUploadService;
use App\Support\DocumentExperienceGuardrails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function preview(Request $request, Document $document): StreamedResponse
    {
        $document = $this->ensureCurrentTenantDocument($document);
        $this->authorize('view', $document);

        abort_unless($this->supportsInlinePreview($document), 404);

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
            ),
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
            'reviewWorkspace' => fn (): array => [
                'preview' => $this->formatPreview($document),
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
     * @return array{supported: bool, url: string|null, mimeType: string|null, fileName: string}
     */
    protected function formatPreview(Document $document): array
    {
        $supported = $this->supportsInlinePreview($document);

        return [
            'supported' => $supported,
            'url' => $supported ? route('documents.preview', $document) : null,
            'mimeType' => $document->mime_type,
            'fileName' => $document->file_name,
        ];
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

    protected function supportsInlinePreview(Document $document): bool
    {
        if ($document->mime_type === 'application/pdf') {
            return true;
        }

        return str_ends_with(strtolower($document->file_name), '.pdf');
    }
}

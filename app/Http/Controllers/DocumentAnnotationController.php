<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreDocumentAnnotationRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\User;
use App\Support\DocumentReviewWorkspacePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentAnnotationController extends Controller
{
    public function store(StoreDocumentAnnotationRequest $request, Document $document): JsonResponse|RedirectResponse
    {
        abort_unless($document->tenant_id === tenant()?->id, 404);

        $this->authorize('view', $document);
        $this->authorize('annotate', $document);

        if (! DocumentReviewWorkspacePresenter::supportsInlinePreview($document)) {
            throw ValidationException::withMessages([
                'document' => 'Annotations are available only for documents with inline PDF previews.',
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        $annotation = $document->annotations()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $user->id,
            'type' => $request->validated('type'),
            'page_number' => $request->validated('page_number'),
            'coordinates' => $request->validated('coordinates'),
            'content' => $request->validated('content'),
        ])->load('user:id,name');

        $activity = $this->logAnnotationAction(
            document: $document,
            request: $request,
            action: 'annotation_created',
            annotation: $annotation,
        );

        if (! $request->expectsJson()) {
            return to_route('documents.show', $document);
        }

        return response()->json([
            'annotation' => DocumentReviewWorkspacePresenter::annotation($annotation, $user->id),
            'activity' => DocumentReviewWorkspacePresenter::auditLog($activity),
        ], 201);
    }

    public function destroy(Request $request, Document $document, DocumentAnnotation $annotation): JsonResponse|RedirectResponse
    {
        abort_unless($document->tenant_id === tenant()?->id, 404);

        $this->authorize('view', $document);

        if ($annotation->tenant_id !== $document->tenant_id || $annotation->document_id !== $document->id) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        if ($annotation->user_id !== $user->id && ! $user->can('approve documents')) {
            abort(403);
        }

        $annotation->loadMissing('user:id,name');

        $annotationSnapshot = clone $annotation;
        $annotationSnapshot->setRelation('user', $annotation->user);

        $annotation->delete();

        $activity = $this->logAnnotationAction(
            document: $document,
            request: $request,
            action: 'annotation_deleted',
            annotation: $annotationSnapshot,
        );

        if (! $request->expectsJson()) {
            return to_route('documents.show', $document);
        }

        return response()->json([
            'annotation_id' => $annotationSnapshot->id,
            'activity' => DocumentReviewWorkspacePresenter::auditLog($activity),
        ]);
    }

    protected function logAnnotationAction(
        Document $document,
        Request $request,
        string $action,
        DocumentAnnotation $annotation,
    ): AuditLog {
        return $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'metadata' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'annotation_id' => $annotation->id,
                'annotation_type' => $annotation->type,
                'page_number' => $annotation->page_number,
            ],
        ])->load('user:id,name');
    }
}

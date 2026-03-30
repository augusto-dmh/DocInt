<?php

namespace App\Http\Controllers;

use App\Events\DocumentCommentUpdated;
use App\Http\Requests\Documents\StoreDocumentCommentRequest;
use App\Http\Requests\Documents\UpdateDocumentCommentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use App\Support\DocumentReviewWorkspacePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentCommentController extends Controller
{
    public function index(Request $request, Document $document): JsonResponse
    {
        $document = $this->ensureCurrentTenantDocument($document);
        $this->authorize('view', $document);

        return response()->json([
            'comments' => $document->comments()
                ->with('user:id,name')
                ->oldest()
                ->get()
                ->map(fn (DocumentComment $comment): array => DocumentReviewWorkspacePresenter::comment($comment))
                ->values(),
        ]);
    }

    public function store(StoreDocumentCommentRequest $request, Document $document): JsonResponse
    {
        $document = $this->ensureCurrentTenantDocument($document);
        $this->authorize('view', $document);
        $this->authorize('comment', $document);

        /** @var User $user */
        $user = $request->user();
        $parentComment = $this->resolveParentComment(
            $request->validated('parent_id'),
            $document,
        );

        $comment = $document->comments()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $user->id,
            'parent_id' => $parentComment?->id,
            'body' => $request->validated('body'),
        ])->load('user:id,name');

        $activity = $this->logCommentAction(
            document: $document,
            request: $request,
            action: 'comment_created',
            comment: $comment,
        );

        event(new DocumentCommentUpdated(
            document: $document,
            action: 'created',
            comment: $comment,
            activity: $activity,
        ));

        return response()->json([
            'comment' => DocumentReviewWorkspacePresenter::comment($comment),
            'activity' => DocumentReviewWorkspacePresenter::auditLog($activity),
        ], 201);
    }

    public function update(
        UpdateDocumentCommentRequest $request,
        Document $document,
        DocumentComment $comment,
    ): JsonResponse {
        $document = $this->ensureCurrentTenantDocument($document);
        $comment = $this->ensureCurrentTenantDocumentComment($document, $comment);
        $this->authorize('view', $document);
        $this->authorize('comment', $document);

        /** @var User $user */
        $user = $request->user();

        abort_unless($comment->user_id === $user->id, 403);

        $comment->update([
            'body' => $request->validated('body'),
        ]);
        $comment->load('user:id,name');

        $activity = $this->logCommentAction(
            document: $document,
            request: $request,
            action: 'comment_updated',
            comment: $comment,
        );

        event(new DocumentCommentUpdated(
            document: $document,
            action: 'updated',
            comment: $comment,
            activity: $activity,
        ));

        return response()->json([
            'comment' => DocumentReviewWorkspacePresenter::comment($comment),
            'activity' => DocumentReviewWorkspacePresenter::auditLog($activity),
        ]);
    }

    public function destroy(
        Request $request,
        Document $document,
        DocumentComment $comment,
    ): JsonResponse {
        $document = $this->ensureCurrentTenantDocument($document);
        $comment = $this->ensureCurrentTenantDocumentComment($document, $comment);
        $this->authorize('view', $document);

        /** @var User $user */
        $user = $request->user();

        if (
            $comment->user_id !== $user->id
            && ! $user->can('moderateComments', $document)
        ) {
            abort(403);
        }

        $comment->loadMissing('user:id,name');

        $commentSnapshot = clone $comment;
        $commentSnapshot->setRelation('user', $comment->user);

        $comment->delete();

        $activity = $this->logCommentAction(
            document: $document,
            request: $request,
            action: 'comment_deleted',
            comment: $commentSnapshot,
        );

        event(new DocumentCommentUpdated(
            document: $document,
            action: 'deleted',
            comment: $commentSnapshot,
            activity: $activity,
        ));

        return response()->json([
            'comment_id' => $commentSnapshot->id,
            'activity' => DocumentReviewWorkspacePresenter::auditLog($activity),
        ]);
    }

    protected function ensureCurrentTenantDocument(Document $document): Document
    {
        abort_unless($document->tenant_id === tenant()?->id, 404);

        return $document;
    }

    protected function ensureCurrentTenantDocumentComment(
        Document $document,
        DocumentComment $comment,
    ): DocumentComment {
        abort_unless(
            $comment->tenant_id === $document->tenant_id
                && $comment->document_id === $document->id,
            404,
        );

        return $comment;
    }

    protected function resolveParentComment(mixed $parentId, Document $document): ?DocumentComment
    {
        if (! is_numeric($parentId)) {
            return null;
        }

        /** @var DocumentComment */
        return $document->comments()
            ->whereKey((int) $parentId)
            ->firstOrFail();
    }

    protected function logCommentAction(
        Document $document,
        Request $request,
        string $action,
        DocumentComment $comment,
    ): AuditLog {
        return $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'metadata' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'comment_id' => $comment->id,
                'parent_id' => $comment->parent_id,
                'body_excerpt' => $comment->body,
            ],
        ])->load('user:id,name');
    }
}

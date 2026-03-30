<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAnnotation;

class DocumentReviewWorkspacePresenter
{
    /**
     * @return array{supported: bool, url: string|null, mimeType: string|null, fileName: string}
     */
    public static function preview(Document $document): array
    {
        $supported = self::supportsInlinePreview($document);

        return [
            'supported' => $supported,
            'url' => $supported ? route('documents.preview', $document) : null,
            'mimeType' => $document->mime_type,
            'fileName' => $document->file_name,
        ];
    }

    public static function supportsInlinePreview(Document $document): bool
    {
        if ($document->mime_type === 'application/pdf') {
            return true;
        }

        return str_ends_with(strtolower($document->file_name), '.pdf');
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     page_number: int,
     *     coordinates: array{x: float|int|string, y: float|int|string, width: float|int|string, height: float|int|string},
     *     content: string|null,
     *     created_at: string,
     *     updated_at: string,
     *     user: array{id: int, name: string}|null,
     *     is_owner: bool
     * }
     */
    public static function annotation(DocumentAnnotation $annotation, ?int $currentUserId): array
    {
        /** @var array{x: float|int|string, y: float|int|string, width: float|int|string, height: float|int|string} $coordinates */
        $coordinates = is_array($annotation->coordinates) ? $annotation->coordinates : [
            'x' => 0,
            'y' => 0,
            'width' => 0,
            'height' => 0,
        ];

        return [
            'id' => $annotation->id,
            'type' => $annotation->type,
            'page_number' => $annotation->page_number,
            'coordinates' => $coordinates,
            'content' => $annotation->content,
            'created_at' => $annotation->created_at->toISOString(),
            'updated_at' => $annotation->updated_at->toISOString(),
            'user' => $annotation->user
                ? [
                    'id' => $annotation->user->id,
                    'name' => $annotation->user->name,
                ]
                : null,
            'is_owner' => $currentUserId !== null && $annotation->user_id === $currentUserId,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     action: string,
     *     details: string|null,
     *     created_at: string,
     *     user: array{id: int, name: string}|null,
     *     ip_address: string|null
     * }
     */
    public static function auditLog(AuditLog $auditLog): array
    {
        $metadata = is_array($auditLog->metadata) ? $auditLog->metadata : [];

        return [
            'id' => $auditLog->id,
            'action' => $auditLog->action,
            'details' => self::details($auditLog->action, $metadata),
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
     * @param  array<string, mixed>  $metadata
     */
    protected static function details(string $action, array $metadata): ?string
    {
        if (! in_array($action, ['annotation_created', 'annotation_deleted'], true)) {
            return null;
        }

        $annotationType = is_string($metadata['annotation_type'] ?? null)
            ? $metadata['annotation_type']
            : null;
        $pageNumber = is_numeric($metadata['page_number'] ?? null)
            ? (int) $metadata['page_number']
            : null;

        if ($annotationType === null || $pageNumber === null) {
            return null;
        }

        return sprintf(
            '%s on page %d',
            ucfirst(str_replace('_', ' ', $annotationType)),
            $pageNumber,
        );
    }
}

<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Events\DocumentProcessingEvent;
use App\Events\DocumentStatusUpdated;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentUploadService
{
    protected const string PENDING_FILE_PATH = 'pending';

    public function __construct(
        protected ProcessingEventRecorder $processingEventRecorder,
    ) {}

    public function upload(UploadedFile $file, Matter $matter, User $user, string $title): Document
    {
        $traceId = (string) Str::uuid();

        $document = DB::transaction(function () use ($file, $matter, $traceId, $title, $user): Document {
            $document = Document::query()->create([
                'tenant_id' => $matter->tenant_id,
                'matter_id' => $matter->id,
                'uploaded_by' => $user->id,
                'title' => $title,
                'file_path' => self::PENDING_FILE_PATH,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                'file_size' => $file->getSize() ?? 0,
                'status' => DocumentStatus::Uploaded,
                'processing_trace_id' => $traceId,
            ]);

            $document->auditLogs()->create([
                'tenant_id' => $matter->tenant_id,
                'user_id' => $user->id,
                'action' => 'uploaded',
                'metadata' => [
                    'original_filename' => $document->file_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->file_size,
                ],
            ]);

            return $document;
        });

        $filePath = $this->buildFilePath($matter->tenant_id, $document->id, $file);

        try {
            $storedPath = Storage::disk('s3')->putFileAs(
                dirname($filePath),
                $file,
                basename($filePath),
            );
        } catch (Throwable $throwable) {
            $this->deletePendingDocumentRecord($document);

            throw $throwable;
        }

        if ($storedPath === false) {
            $this->deletePendingDocumentRecord($document);

            throw new RuntimeException('Failed to store document on S3.');
        }

        try {
            /**
             * @var array{
             *     document: Document,
             *     message_id: string,
             *     trace_id: string,
             *     metadata: array{
             *         original_filename: string,
             *         mime_type: string|null,
             *         size_bytes: int,
             *         uploaded_by_user_id: int
             *     }
             * } $uploadResult
             */
            $uploadResult = DB::transaction(function () use ($document, $storedPath, $user): array {
                $messageId = (string) Str::uuid();

                $document->update(['file_path' => $storedPath]);

                $metadata = [
                    'original_filename' => $document->file_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->file_size,
                    'uploaded_by_user_id' => $user->id,
                ];

                $processingEvent = $this->processingEventRecorder->record(
                    $document,
                    $messageId,
                    'upload-dispatch',
                    'document.uploaded',
                    null,
                    DocumentStatus::Uploaded,
                    $document->processing_trace_id,
                    $metadata,
                );

                return [
                    'document' => $document,
                    'message_id' => $messageId,
                    'trace_id' => $processingEvent->trace_id,
                    'metadata' => $metadata,
                ];
            });
        } catch (Throwable $throwable) {
            Storage::disk('s3')->delete($storedPath);
            $this->deletePendingDocumentRecord($document);

            throw $throwable;
        }

        /** @var Document $freshDocument */
        $freshDocument = $uploadResult['document']->fresh(['matter', 'uploader']);
        $uploadResult['document'] = $freshDocument;

        event(new DocumentProcessingEvent(
            messageId: $uploadResult['message_id'],
            traceId: $uploadResult['trace_id'],
            tenantId: $uploadResult['document']->tenant_id,
            documentId: $uploadResult['document']->id,
            event: 'document.uploaded',
            timestamp: now()->toImmutable(),
            metadata: $uploadResult['metadata'],
            retryCount: 0,
        ));

        event(new DocumentStatusUpdated(
            document: $uploadResult['document'],
            fromStatus: null,
            toStatus: DocumentStatus::Uploaded->value,
            traceId: $uploadResult['trace_id'],
        ));

        return $uploadResult['document'];
    }

    public function generatePresignedUrl(Document $document, int $ttlMinutes = 15): string
    {
        return Storage::disk('s3')->temporaryUrl(
            $document->file_path,
            now()->addMinutes($ttlMinutes),
        );
    }

    public function delete(Document $document, User $user): void
    {
        $filePath = $document->file_path;

        DB::transaction(function () use ($document, $user): void {
            $document->auditLogs()->create([
                'tenant_id' => $document->tenant_id,
                'user_id' => $user->id,
                'action' => 'deleted',
                'metadata' => null,
            ]);

            $document->delete();
        });

        if ($filePath !== '' && $filePath !== self::PENDING_FILE_PATH) {
            Storage::disk('s3')->delete($filePath);
        }
    }

    protected function deletePendingDocumentRecord(Document $document): void
    {
        DB::transaction(function () use ($document): void {
            $document->auditLogs()->delete();
            $document->delete();
        });
    }

    protected function buildFilePath(string $tenantId, int $documentId, UploadedFile $file): string
    {
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $slug = Str::slug($filename);

        if ($slug === '') {
            $slug = 'document';
        }

        return "tenants/{$tenantId}/documents/{$documentId}/{$slug}.{$extension}";
    }
}

<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentUploadService
{
    public function upload(UploadedFile $file, Matter $matter, User $user, string $title): Document
    {
        return DB::transaction(function () use ($file, $matter, $user, $title): Document {
            $document = Document::query()->create([
                'tenant_id' => $matter->tenant_id,
                'matter_id' => $matter->id,
                'uploaded_by' => $user->id,
                'title' => $title,
                'file_path' => 'pending',
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize() ?? 0,
                'status' => 'uploaded',
            ]);

            $filePath = $this->buildFilePath($matter->tenant_id, $document->id, $file);
            $storedPath = Storage::disk('s3')->putFileAs(
                dirname($filePath),
                $file,
                basename($filePath),
            );

            if ($storedPath === false) {
                throw new RuntimeException('Failed to store document on S3.');
            }

            $document->update([
                'file_path' => $storedPath,
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

            return $document->fresh(['matter', 'uploader']);
        });
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
        if ($document->file_path !== '' && $document->file_path !== 'pending') {
            Storage::disk('s3')->delete($document->file_path);
        }

        $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $user->id,
            'action' => 'deleted',
            'metadata' => null,
        ]);
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

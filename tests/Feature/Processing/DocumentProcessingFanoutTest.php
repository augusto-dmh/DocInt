<?php

use App\Events\DocumentProcessingEvent;
use App\Jobs\Processing\AuditLogConsumerJob;
use App\Jobs\Processing\OcrExtractionConsumerJob;
use App\Jobs\Processing\VirusScanConsumerJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function processingEventPayload(string $event): DocumentProcessingEvent
{
    return new DocumentProcessingEvent(
        messageId: (string) Str::uuid(),
        traceId: (string) Str::uuid(),
        tenantId: 'tenant-123',
        documentId: 1,
        event: $event,
        timestamp: CarbonImmutable::now(),
        metadata: [
            'original_filename' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_by_user_id' => 1,
        ],
        retryCount: 0,
    );
}

test('upload event fans out virus scan audit log and ocr consumers', function (): void {
    Queue::fake();

    event(processingEventPayload('document.uploaded'));

    Queue::assertPushed(VirusScanConsumerJob::class, function (VirusScanConsumerJob $job): bool {
        return $job->queue === 'queue.virus-scan';
    });

    Queue::assertPushed(AuditLogConsumerJob::class, function (AuditLogConsumerJob $job): bool {
        return $job->queue === 'queue.audit-log';
    });

    Queue::assertPushed(OcrExtractionConsumerJob::class, function (OcrExtractionConsumerJob $job): bool {
        return $job->queue === 'queue.ocr-extraction';
    });

    Queue::assertPushed(VirusScanConsumerJob::class, 1);
    Queue::assertPushed(AuditLogConsumerJob::class, 1);
    Queue::assertPushed(OcrExtractionConsumerJob::class, 1);
});

test('non upload processing events do not trigger fanout jobs', function (): void {
    Queue::fake();

    event(processingEventPayload('document.status.transitioned'));

    Queue::assertNothingPushed();
});

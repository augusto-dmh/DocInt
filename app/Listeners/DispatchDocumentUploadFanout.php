<?php

namespace App\Listeners;

use App\Events\DocumentProcessingEvent;
use App\Jobs\Processing\AuditLogConsumerJob;
use App\Jobs\Processing\OcrExtractionConsumerJob;
use App\Jobs\Processing\VirusScanConsumerJob;

class DispatchDocumentUploadFanout
{
    public function handle(DocumentProcessingEvent $event): void
    {
        if ($event->event !== 'document.uploaded') {
            return;
        }

        $payload = $event->toPayload();
        $connection = $this->resolveQueueConnection();

        VirusScanConsumerJob::dispatch($payload)
            ->onConnection($connection)
            ->onQueue('queue.virus-scan');

        AuditLogConsumerJob::dispatch($payload)
            ->onConnection($connection)
            ->onQueue('queue.audit-log');

        OcrExtractionConsumerJob::dispatch($payload)
            ->onConnection($connection)
            ->onQueue('queue.ocr-extraction');
    }

    protected function resolveQueueConnection(): string
    {
        $configuredConnection = config('processing.queue_connection', config('queue.default', 'sync'));

        return is_string($configuredConnection) && $configuredConnection !== ''
            ? $configuredConnection
            : 'sync';
    }
}

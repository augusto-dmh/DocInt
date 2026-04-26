<?php

namespace App\Services\Documents;

use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\ProcessingEvent;

class DocumentShowPresenter
{
    /**
     * @return array{
     *     id: int,
     *     consumer_name: string,
     *     status_from: string|null,
     *     status_to: string|null,
     *     event: string,
     *     created_at: string
     * }
     */
    public function formatProcessingEvent(ProcessingEvent $processingEvent): array
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
    public function formatExtractedData(?ExtractedData $extractedData): ?array
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
    public function formatClassification(?DocumentClassification $classification): ?array
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
}

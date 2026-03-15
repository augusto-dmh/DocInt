<?php

namespace App\Services\Processing;

use App\Models\Document;
use App\Models\ExtractedData;

interface ClassificationProvider
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     type: string,
     *     confidence: float|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function classify(Document $document, ?ExtractedData $extractedData, array $payload): array;
}

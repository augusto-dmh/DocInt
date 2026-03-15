<?php

namespace App\Services\Processing;

use App\Models\Document;

interface OcrProvider
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     extracted_text: string|null,
     *     payload: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     classification_hint: string|null
     * }
     */
    public function extract(Document $document, array $payload): array;
}

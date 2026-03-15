<?php

namespace App\Services\Processing;

class ClassificationRoutingResolver
{
    /**
     * @return list<string>
     */
    public function supportedTypes(): array
    {
        return ['contract', 'tax', 'invoice', 'general'];
    }

    public function resolveTypeHint(?string $hint, string $fileName, ?string $text = null): string
    {
        $normalizedHint = $this->normalizeType($hint);

        if ($normalizedHint !== null) {
            return $normalizedHint;
        }

        $haystack = strtolower($fileName.' '.($text ?? ''));

        if (str_contains($haystack, 'invoice') || str_contains($haystack, 'receipt')) {
            return 'invoice';
        }

        if (str_contains($haystack, 'tax') || str_contains($haystack, 'irs') || str_contains($haystack, 'w-2')) {
            return 'tax';
        }

        if (str_contains($haystack, 'agreement') || str_contains($haystack, 'contract') || str_contains($haystack, 'nda')) {
            return 'contract';
        }

        return 'general';
    }

    public function resolveQueueForType(string $type): string
    {
        $queues = config('processing.classification_queues');
        $normalizedType = $this->normalizeType($type) ?? 'general';

        if (! is_array($queues)) {
            return 'queue.classify.general';
        }

        $queue = $queues[$normalizedType] ?? $queues['general'] ?? 'queue.classify.general';

        return is_string($queue) && $queue !== ''
            ? $queue
            : 'queue.classify.general';
    }

    public function normalizeType(?string $type): ?string
    {
        if (! is_string($type) || trim($type) === '') {
            return null;
        }

        $normalized = strtolower(trim($type));

        return in_array($normalized, $this->supportedTypes(), true)
            ? $normalized
            : null;
    }
}

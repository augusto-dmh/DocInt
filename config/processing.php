<?php

$retryBackoff = array_values(array_filter(
    array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) env('PROCESSING_RETRY_BACKOFF', '5,15,45')),
    ),
    static fn (int $seconds): bool => $seconds > 0,
));

if ($retryBackoff === []) {
    $retryBackoff = [5, 15, 45];
}

return [
    'queue_connection' => env('PROCESSING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
    'ocr_provider' => env('PROCESSING_OCR_PROVIDER', 'simulated'),
    'classification_provider' => env('PROCESSING_CLASSIFICATION_PROVIDER', 'simulated'),
    'retry_attempts' => (int) env('PROCESSING_RETRY_ATTEMPTS', 3),
    'retry_backoff' => $retryBackoff,
    'scan_wait_delay_seconds' => (int) env('PROCESSING_SCAN_WAIT_DELAY_SECONDS', 5),
    'circuit_breaker' => [
        'enabled' => filter_var(env('PROCESSING_CIRCUIT_BREAKER_ENABLED', true), FILTER_VALIDATE_BOOL),
        'failure_threshold' => (int) env('PROCESSING_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('PROCESSING_CIRCUIT_BREAKER_COOLDOWN_SECONDS', 60),
    ],
];

<?php

$ocrProvider = strtolower(trim((string) env('PROCESSING_OCR_PROVIDER', 'openai')));
$classificationProvider = strtolower(trim((string) env('PROCESSING_CLASSIFICATION_PROVIDER', 'openai')));

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
    'queue_connection' => env('PROCESSING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'rabbitmq')),
    'ocr_provider' => $ocrProvider,
    'classification_provider' => $classificationProvider,
    'supported_ocr_providers' => ['openai'],
    'supported_classification_providers' => ['openai'],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('PROCESSING_OPENAI_MODEL', 'gpt-4o-mini'),
        'ocr_model' => env('PROCESSING_OPENAI_OCR_MODEL', env('PROCESSING_OPENAI_MODEL', 'gpt-4o-mini')),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout_seconds' => (int) env('PROCESSING_OPENAI_TIMEOUT', 30),
        'ocr_max_source_characters' => (int) env('PROCESSING_OPENAI_OCR_MAX_SOURCE_CHARACTERS', 3000),
    ],
    'provider_circuit' => [
        'failure_threshold' => (int) env('PROCESSING_PROVIDER_CIRCUIT_FAILURE_THRESHOLD', 3),
        'cooldown_seconds' => (int) env('PROCESSING_PROVIDER_CIRCUIT_COOLDOWN_SECONDS', 60),
    ],
    'provider_degraded_requeue_delay_seconds' => (int) env('PROCESSING_PROVIDER_DEGRADED_REQUEUE_DELAY_SECONDS', 30),
    'retry_attempts' => (int) env('PROCESSING_RETRY_ATTEMPTS', 3),
    'retry_backoff' => $retryBackoff,
    'scan_wait_delay_seconds' => (int) env('PROCESSING_SCAN_WAIT_DELAY_SECONDS', 5),
    'runtime_required_contract' => [
        'exact' => [
            [
                'path' => 'processing.ocr_provider',
                'env' => 'PROCESSING_OCR_PROVIDER',
                'expected' => 'openai',
            ],
            [
                'path' => 'processing.classification_provider',
                'env' => 'PROCESSING_CLASSIFICATION_PROVIDER',
                'expected' => 'openai',
            ],
            [
                'path' => 'filesystems.default',
                'env' => 'FILESYSTEM_DISK',
                'expected' => 's3',
            ],
            [
                'path' => 'processing.queue_connection',
                'env' => 'PROCESSING_QUEUE_CONNECTION',
                'expected' => 'rabbitmq',
            ],
        ],
        'non_empty' => [
            [
                'path' => 'filesystems.disks.s3.key',
                'env' => 'AWS_ACCESS_KEY_ID',
            ],
            [
                'path' => 'filesystems.disks.s3.secret',
                'env' => 'AWS_SECRET_ACCESS_KEY',
            ],
            [
                'path' => 'filesystems.disks.s3.region',
                'env' => 'AWS_DEFAULT_REGION',
            ],
            [
                'path' => 'filesystems.disks.s3.bucket',
                'env' => 'AWS_BUCKET',
            ],
            [
                'path' => 'processing.openai.api_key',
                'env' => 'OPENAI_API_KEY',
            ],
        ],
    ],
    'classification_queues' => [
        'contract' => 'queue.classify.contract',
        'tax' => 'queue.classify.tax',
        'invoice' => 'queue.classify.invoice',
        'general' => 'queue.classify.general',
    ],
];

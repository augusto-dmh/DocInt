<?php

test('processing config exposes openai runtime defaults for pipeline consumers', function (): void {
    expect(config('processing.queue_connection'))->toBe(config('queue.default'))
        ->and(config('processing.ocr_provider'))->toBe('openai')
        ->and(config('processing.classification_provider'))->toBe('openai')
        ->and(config('processing.supported_ocr_providers'))->toBe(['openai'])
        ->and(config('processing.supported_classification_providers'))->toBe(['openai'])
        ->and(config('processing.openai'))->toBe([
            'api_key' => 'test-openai-key',
            'model' => 'gpt-4o-mini',
            'ocr_model' => 'gpt-4o-mini',
            'base_url' => 'https://api.openai.com/v1',
            'timeout_seconds' => 30,
            'ocr_max_source_characters' => 3000,
        ])
        ->and(config('processing.retry_attempts'))->toBe(3)
        ->and(config('processing.retry_backoff'))->toBe([5, 15, 45])
        ->and(config('processing.scan_wait_delay_seconds'))->toBe(5)
        ->and(config('processing.provider_degraded_requeue_delay_seconds'))->toBe(30)
        ->and(config('processing.provider_circuit'))->toBe([
            'failure_threshold' => 3,
            'cooldown_seconds' => 60,
        ])
        ->and(config('processing.runtime_required_contract'))->toBe([
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
        ])
        ->and(config('processing.classification_queues'))->toBe([
            'contract' => 'queue.classify.contract',
            'tax' => 'queue.classify.tax',
            'invoice' => 'queue.classify.invoice',
            'general' => 'queue.classify.general',
        ]);
});

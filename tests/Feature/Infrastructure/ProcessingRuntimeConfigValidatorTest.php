<?php

use App\Support\ProcessingRuntimeConfigValidator;

test('runtime validator reports missing required unified development keys', function (): void {
    config()->set('processing.ocr_provider', 'simulated');
    config()->set('processing.classification_provider', 'simulated');
    config()->set('filesystems.default', 'local');
    config()->set('processing.queue_connection', 'sync');
    config()->set('filesystems.disks.s3.key', '');
    config()->set('filesystems.disks.s3.secret', '');
    config()->set('filesystems.disks.s3.region', '');
    config()->set('filesystems.disks.s3.bucket', '');
    config()->set('processing.openai.api_key', '');

    try {
        app(ProcessingRuntimeConfigValidator::class)->validateOrFail();
        $this->fail('Expected runtime validation to fail.');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())
            ->toContain('PROCESSING_OCR_PROVIDER must be set to [openai].')
            ->toContain('PROCESSING_CLASSIFICATION_PROVIDER must be set to [openai].')
            ->toContain('FILESYSTEM_DISK must be set to [s3].')
            ->toContain('PROCESSING_QUEUE_CONNECTION must be set to [rabbitmq].')
            ->toContain('AWS_ACCESS_KEY_ID must be set for development runtime.')
            ->toContain('AWS_SECRET_ACCESS_KEY must be set for development runtime.')
            ->toContain('AWS_BUCKET must be set for development runtime.')
            ->toContain('OPENAI_API_KEY must be set for development runtime.');
    }
});

test('runtime validator accepts the unified development profile', function (): void {
    config()->set('processing.ocr_provider', 'openai');
    config()->set('processing.classification_provider', 'openai');
    config()->set('filesystems.default', 's3');
    config()->set('processing.queue_connection', 'rabbitmq');
    config()->set('filesystems.disks.s3.key', 'aws-key');
    config()->set('filesystems.disks.s3.secret', 'aws-secret');
    config()->set('filesystems.disks.s3.region', 'us-east-1');
    config()->set('filesystems.disks.s3.bucket', 'docintern-dev');
    config()->set('processing.openai.api_key', 'test-openai-key');

    app(ProcessingRuntimeConfigValidator::class)->validateOrFail();

    expect(true)->toBeTrue();
});

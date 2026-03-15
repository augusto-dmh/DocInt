<?php

use Symfony\Component\Console\Command\Command as SymfonyCommand;

test('environment check command passes when unified runtime contracts are valid', function (): void {
    config()->set('processing.ocr_provider', 'openai');
    config()->set('processing.classification_provider', 'openai');
    config()->set('filesystems.default', 's3');
    config()->set('processing.queue_connection', 'rabbitmq');
    config()->set('filesystems.disks.s3.key', 'aws-key');
    config()->set('filesystems.disks.s3.secret', 'aws-secret');
    config()->set('filesystems.disks.s3.region', 'us-east-1');
    config()->set('filesystems.disks.s3.bucket', 'docintern-dev');
    config()->set('processing.openai.api_key', 'test-openai-key');

    $this->artisan('docintern:cutover-check')
        ->expectsOutputToContain('Runtime profile: development-first')
        ->expectsOutputToContain('Environment contract check passed.')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

test('environment check command fails when runtime contracts are invalid', function (): void {
    config()->set('processing.ocr_provider', 'simulated');
    config()->set('processing.classification_provider', 'simulated');
    config()->set('filesystems.default', 'local');
    config()->set('processing.queue_connection', 'sync');
    config()->set('filesystems.disks.s3.key', '');
    config()->set('filesystems.disks.s3.secret', '');
    config()->set('filesystems.disks.s3.region', '');
    config()->set('filesystems.disks.s3.bucket', '');
    config()->set('processing.openai.api_key', '');

    $this->artisan('docintern:cutover-check')
        ->expectsOutputToContain('Runtime profile: development-first')
        ->expectsOutputToContain('Environment contract check failed.')
        ->expectsOutputToContain('PROCESSING_OCR_PROVIDER must be set to [openai].')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

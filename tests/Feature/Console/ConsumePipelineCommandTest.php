<?php

use App\Support\RabbitMqPipelineMap;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

test('consume command resolves supported pipelines in dry run mode', function (string $pipeline): void {
    $expectedQueue = RabbitMqPipelineMap::queueListFor($pipeline);
    $expectedTimeout = RabbitMqPipelineMap::defaultTimeoutFor($pipeline);

    $this->artisan('docintern:consume', [
        'pipeline' => $pipeline,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('RabbitMQ pipeline consumer dry run')
        ->expectsOutputToContain('Pipeline: '.$pipeline)
        ->expectsOutputToContain('Connection: rabbitmq')
        ->expectsOutputToContain('Queue: '.$expectedQueue)
        ->expectsOutputToContain('Timeout: '.$expectedTimeout)
        ->assertExitCode(SymfonyCommand::SUCCESS);
})->with(RabbitMqPipelineMap::all());

test('consume command rejects unsupported pipeline', function (): void {
    $this->artisan('docintern:consume', [
        'pipeline' => 'unknown-pipeline',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Unsupported pipeline [unknown-pipeline].')
        ->expectsOutputToContain('Supported pipelines: '.implode(', ', RabbitMqPipelineMap::all()))
        ->assertExitCode(SymfonyCommand::INVALID);
});

test('consume command applies option overrides in dry run mode', function (): void {
    $this->artisan('docintern:consume', [
        'pipeline' => 'virus-scan',
        '--sleep' => 5,
        '--tries' => 4,
        '--timeout' => 45,
        '--memory' => 512,
        '--queue' => 'queue.custom.scan',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Queue: queue.custom.scan')
        ->expectsOutputToContain('Sleep: 5')
        ->expectsOutputToContain('Tries: 4')
        ->expectsOutputToContain('Timeout: 45')
        ->expectsOutputToContain('Memory: 512')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

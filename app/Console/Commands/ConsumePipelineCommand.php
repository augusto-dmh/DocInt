<?php

namespace App\Console\Commands;

use App\Support\RabbitMqPipelineMap;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class ConsumePipelineCommand extends Command
{
    protected $signature = 'docintern:consume
        {pipeline : The pipeline identifier to consume}
        {--sleep=3 : Seconds to sleep when no job is available}
        {--tries=3 : Maximum retry attempts}
        {--timeout= : Seconds before a job times out}
        {--memory=256 : Memory limit in MB}
        {--queue= : Override queue list (comma separated)}
        {--dry-run : Print resolved queue:work options without running the worker}';

    protected $description = 'Consume a configured RabbitMQ pipeline queue';

    public function handle(): int
    {
        $pipeline = (string) $this->argument('pipeline');

        if (! RabbitMqPipelineMap::has($pipeline)) {
            $this->error('Unsupported pipeline ['.$pipeline.'].');
            $this->line('Supported pipelines: '.implode(', ', RabbitMqPipelineMap::all()));

            return SymfonyCommand::INVALID;
        }

        $queue = $this->resolveQueue($pipeline);
        $options = [
            'connection' => 'rabbitmq',
            '--queue' => $queue,
            '--sleep' => $this->resolveOption('sleep', 3),
            '--tries' => $this->resolveOption('tries', 3),
            '--timeout' => $this->resolveTimeout($pipeline),
            '--memory' => $this->resolveOption('memory', 256),
        ];

        if ((bool) $this->option('dry-run')) {
            $this->line('RabbitMQ pipeline consumer dry run');
            $this->line('Pipeline: '.$pipeline);
            $this->line('Connection: rabbitmq');
            $this->line('Queue: '.$queue);
            $this->line('Sleep: '.$options['--sleep']);
            $this->line('Tries: '.$options['--tries']);
            $this->line('Timeout: '.$options['--timeout']);
            $this->line('Memory: '.$options['--memory']);

            return SymfonyCommand::SUCCESS;
        }

        return (int) $this->call('queue:work', $options);
    }

    protected function resolveQueue(string $pipeline): string
    {
        $queueOption = trim((string) $this->option('queue'));

        if ($queueOption !== '') {
            return $queueOption;
        }

        return RabbitMqPipelineMap::queueListFor($pipeline);
    }

    protected function resolveTimeout(string $pipeline): int
    {
        $timeoutOption = $this->option('timeout');

        if ($timeoutOption === null || $timeoutOption === '') {
            return RabbitMqPipelineMap::defaultTimeoutFor($pipeline);
        }

        return $this->normalizePositiveInt($timeoutOption, RabbitMqPipelineMap::defaultTimeoutFor($pipeline));
    }

    protected function resolveOption(string $name, int $default): int
    {
        return $this->normalizePositiveInt($this->option($name), $default);
    }

    protected function normalizePositiveInt(mixed $value, int $default): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $intValue = (int) $value;

            if ($intValue > 0) {
                return $intValue;
            }
        }

        return $default;
    }
}

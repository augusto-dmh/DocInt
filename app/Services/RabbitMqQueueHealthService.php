<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class RabbitMqQueueHealthService
{
    /**
     * @var array<string, string>
     */
    protected const QUEUE_PIPELINES = [
        'queue.virus-scan' => 'virus-scan',
        'queue.audit-log' => 'audit-log',
        'queue.ocr-extraction' => 'ocr-extraction',
        'queue.classify.contract' => 'classification',
        'queue.classify.tax' => 'classification',
        'queue.classify.invoice' => 'classification',
        'queue.classify.general' => 'classification',
        'queue.notify.email' => 'notifications',
        'queue.notify.inapp' => 'notifications',
        'queue.dead-letters' => 'dead-letters',
    ];

    /**
     * @return array{
     *     available: bool,
     *     generated_at: string,
     *     queues: list<array{
     *         name: string,
     *         pipeline: string,
     *         messages: int,
     *         messages_ready: int,
     *         messages_unacknowledged: int,
     *         consumers: int,
     *         state: string,
     *         is_dead_letter: bool
     *     }>,
     *     summary: array{
     *         total_messages: int,
     *         total_ready: int,
     *         total_unacked: int,
     *         total_consumers: int,
     *         dead_letter_messages: int
     *     },
     *     error: string|null
     * }
     */
    public function snapshot(): array
    {
        $generatedAt = now()->toImmutable()->toISOString();

        try {
            $queueMetrics = $this->fetchQueueMetrics();
        } catch (Throwable) {
            return [
                'available' => false,
                'generated_at' => $generatedAt,
                'queues' => [],
                'summary' => [
                    'total_messages' => 0,
                    'total_ready' => 0,
                    'total_unacked' => 0,
                    'total_consumers' => 0,
                    'dead_letter_messages' => 0,
                ],
                'error' => 'Queue health metrics are currently unavailable.',
            ];
        }

        return [
            'available' => true,
            'generated_at' => $generatedAt,
            'queues' => $queueMetrics['queues'],
            'summary' => $queueMetrics['summary'],
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     queues: list<array{
     *         name: string,
     *         pipeline: string,
     *         messages: int,
     *         messages_ready: int,
     *         messages_unacknowledged: int,
     *         consumers: int,
     *         state: string,
     *         is_dead_letter: bool
     *     }>,
     *     summary: array{
     *         total_messages: int,
     *         total_ready: int,
     *         total_unacked: int,
     *         total_consumers: int,
     *         dead_letter_messages: int
     *     }
     * }
     */
    protected function fetchQueueMetrics(): array
    {
        $managementConfig = $this->resolveManagementConfig();
        $response = Http::timeout($managementConfig['timeout_seconds'])
            ->acceptJson()
            ->withBasicAuth(
                $managementConfig['username'],
                $managementConfig['password'],
            )
            ->baseUrl(sprintf(
                '%s://%s:%d',
                $managementConfig['scheme'],
                $managementConfig['host'],
                $managementConfig['port'],
            ))
            ->get('/api/queues/'.rawurlencode($managementConfig['vhost']));

        if (! $response->successful()) {
            throw new ConnectionException(
                sprintf('RabbitMQ management request failed with status [%d].', $response->status()),
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ConnectionException('RabbitMQ management response payload is invalid.');
        }

        $indexedQueues = [];

        foreach ($payload as $queueRecord) {
            if (! is_array($queueRecord)) {
                continue;
            }

            $queueName = $this->stringValue($queueRecord['name'] ?? null, '');

            if ($queueName === '') {
                continue;
            }

            $indexedQueues[$queueName] = $queueRecord;
        }

        $summary = [
            'total_messages' => 0,
            'total_ready' => 0,
            'total_unacked' => 0,
            'total_consumers' => 0,
            'dead_letter_messages' => 0,
        ];

        $queues = [];

        foreach (self::QUEUE_PIPELINES as $queueName => $pipeline) {
            $queueRecord = is_array($indexedQueues[$queueName] ?? null)
                ? $indexedQueues[$queueName]
                : [];

            $messages = $this->intValue($queueRecord['messages'] ?? null, 0);
            $messagesReady = $this->intValue($queueRecord['messages_ready'] ?? null, 0);
            $messagesUnacknowledged = $this->intValue($queueRecord['messages_unacknowledged'] ?? null, 0);
            $consumers = $this->intValue($queueRecord['consumers'] ?? null, 0);
            $state = $this->stringValue($queueRecord['state'] ?? null, 'unknown');
            $isDeadLetter = $queueName === 'queue.dead-letters';

            $summary['total_messages'] += $messages;
            $summary['total_ready'] += $messagesReady;
            $summary['total_unacked'] += $messagesUnacknowledged;
            $summary['total_consumers'] += $consumers;

            if ($isDeadLetter) {
                $summary['dead_letter_messages'] = $messages;
            }

            $queues[] = [
                'name' => $queueName,
                'pipeline' => $pipeline,
                'messages' => $messages,
                'messages_ready' => $messagesReady,
                'messages_unacknowledged' => $messagesUnacknowledged,
                'consumers' => $consumers,
                'state' => $state,
                'is_dead_letter' => $isDeadLetter,
            ];
        }

        return [
            'queues' => $queues,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{
     *     scheme: string,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     vhost: string,
     *     timeout_seconds: int
     * }
     */
    protected function resolveManagementConfig(): array
    {
        $config = config('queue.connections.rabbitmq.management', []);

        if (! is_array($config)) {
            $config = [];
        }

        return [
            'scheme' => $this->stringValue($config['scheme'] ?? null, 'http'),
            'host' => $this->stringValue($config['host'] ?? null, '127.0.0.1'),
            'port' => $this->intValue($config['port'] ?? null, 15672),
            'username' => $this->stringValue($config['username'] ?? null, 'guest'),
            'password' => $this->stringValue($config['password'] ?? null, 'guest'),
            'vhost' => $this->stringValue($config['vhost'] ?? null, '/'),
            'timeout_seconds' => max(1, $this->intValue($config['timeout_seconds'] ?? null, 5)),
        ];
    }

    protected function intValue(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    protected function stringValue(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : $default;
    }
}

<?php

namespace App\Support;

use InvalidArgumentException;

final class RabbitMqPipelineMap
{
    /**
     * @var array<string, array{queues: list<string>, timeout: int}>
     */
    private const PIPELINES = [
        'virus-scan' => [
            'queues' => ['queue.virus-scan'],
            'timeout' => 60,
        ],
        'audit-log' => [
            'queues' => ['queue.audit-log'],
            'timeout' => 30,
        ],
        'ocr-extraction' => [
            'queues' => ['queue.ocr-extraction'],
            'timeout' => 300,
        ],
        'classification' => [
            'queues' => [
                'queue.classify.contract',
                'queue.classify.tax',
                'queue.classify.invoice',
                'queue.classify.general',
            ],
            'timeout' => 120,
        ],
        'dead-letters' => [
            'queues' => ['queue.dead-letters'],
            'timeout' => 60,
        ],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::PIPELINES);
    }

    public static function has(string $pipeline): bool
    {
        return array_key_exists($pipeline, self::PIPELINES);
    }

    /**
     * @return list<string>
     */
    public static function queuesFor(string $pipeline): array
    {
        if (! self::has($pipeline)) {
            throw new InvalidArgumentException("Unsupported pipeline [{$pipeline}].");
        }

        return self::PIPELINES[$pipeline]['queues'];
    }

    public static function queueListFor(string $pipeline): string
    {
        return implode(',', self::queuesFor($pipeline));
    }

    public static function defaultTimeoutFor(string $pipeline): int
    {
        if (! self::has($pipeline)) {
            throw new InvalidArgumentException("Unsupported pipeline [{$pipeline}].");
        }

        return self::PIPELINES[$pipeline]['timeout'];
    }
}

<?php

test('queue config defines rabbitmq connection with expected defaults', function (): void {
    $connection = config('queue.connections.rabbitmq');

    expect($connection)->toBeArray()
        ->and($connection['driver'])->toBe('rabbitmq')
        ->and($connection['queue'])->toBe('default')
        ->and($connection['worker'])->toBe('default')
        ->and($connection['connection'])->toBe('default')
        ->and($connection['hosts'])->toBeArray()
        ->and($connection['hosts'][0])->toBeArray()
        ->and($connection['hosts'][0]['host'])->toBe('rabbitmq')
        ->and($connection['hosts'][0]['port'])->toBe(5672)
        ->and($connection['hosts'][0]['user'])->toBe((string) env('RABBITMQ_USER', 'guest'))
        ->and($connection['hosts'][0]['password'])->toBe((string) env('RABBITMQ_PASSWORD', 'guest'))
        ->and($connection['hosts'][0]['vhost'])->toBe('/docintern')
        ->and($connection['options'])->toBeArray()
        ->and($connection['options']['queue'])->toBeArray()
        ->and($connection['options']['queue']['reroute_failed'])->toBeTrue()
        ->and($connection['options']['queue']['failed_exchange'])->toBe('docintern.dlx')
        ->and($connection['options']['queue']['failed_routing_key'])->toBe('dlq.%s')
        ->and($connection['management'])->toBeArray()
        ->and($connection['management']['scheme'])->toBe('http')
        ->and($connection['management']['host'])->toBe('rabbitmq')
        ->and($connection['management']['port'])->toBe(15672)
        ->and($connection['management']['username'])->toBe((string) env('RABBITMQ_MANAGEMENT_USER', env('RABBITMQ_USER', 'guest')))
        ->and($connection['management']['password'])->toBe((string) env('RABBITMQ_MANAGEMENT_PASSWORD', env('RABBITMQ_PASSWORD', 'guest')))
        ->and($connection['management']['vhost'])->toBe('/docintern')
        ->and($connection['management']['timeout_seconds'])->toBe(5);
});

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
        ->and($connection['hosts'][0]['user'])->toBe('guest')
        ->and($connection['hosts'][0]['password'])->toBe('guest')
        ->and($connection['hosts'][0]['vhost'])->toBe('/docintern')
        ->and($connection['options'])->toBeArray()
        ->and($connection['options']['queue'])->toBeArray()
        ->and($connection['options']['queue']['reroute_failed'])->toBeTrue()
        ->and($connection['options']['queue']['failed_exchange'])->toBe('docintern.dlx')
        ->and($connection['options']['queue']['failed_routing_key'])->toBe('dlq.%s');
});

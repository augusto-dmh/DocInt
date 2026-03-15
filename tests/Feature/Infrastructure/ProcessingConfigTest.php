<?php

test('processing config exposes development defaults for foundation services', function (): void {
    expect(config('processing.queue_connection'))->toBe(config('queue.default'))
        ->and(config('processing.ocr_provider'))->toBe('simulated')
        ->and(config('processing.classification_provider'))->toBe('simulated')
        ->and(config('processing.retry_attempts'))->toBe(3)
        ->and(config('processing.retry_backoff'))->toBe([5, 15, 45])
        ->and(config('processing.scan_wait_delay_seconds'))->toBe(5)
        ->and(config('processing.circuit_breaker'))->toBe([
            'enabled' => true,
            'failure_threshold' => 5,
            'cooldown_seconds' => 60,
        ]);
});

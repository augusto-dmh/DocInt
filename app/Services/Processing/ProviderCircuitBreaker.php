<?php

namespace App\Services\Processing;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProviderCircuitBreaker
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $operation
     * @param  Closure(Throwable): bool  $isTransient
     * @return TReturn
     */
    public function run(string $provider, Closure $operation, Closure $isTransient): mixed
    {
        if ($this->isOpen($provider)) {
            throw new ProviderDegradedException($provider, 'circuit_breaker_open');
        }

        try {
            $result = $operation();
            $this->reset($provider);

            return $result;
        } catch (Throwable $throwable) {
            if (! $isTransient($throwable)) {
                throw $throwable;
            }

            $this->recordFailure($provider);

            throw ProviderDegradedException::fromThrowable($provider, $throwable);
        }
    }

    public function isOpen(string $provider): bool
    {
        $openUntilTimestamp = (int) Cache::get($this->openUntilKey($provider), 0);

        if ($openUntilTimestamp <= 0) {
            return false;
        }

        if (now()->getTimestamp() < $openUntilTimestamp) {
            return true;
        }

        $this->reset($provider);

        return false;
    }

    public function reset(string $provider): void
    {
        Cache::forget($this->failureCountKey($provider));
        Cache::forget($this->openUntilKey($provider));
    }

    protected function recordFailure(string $provider): void
    {
        $failureCount = (int) Cache::get($this->failureCountKey($provider), 0) + 1;
        $cooldownSeconds = $this->cooldownSeconds();
        $failureTtl = now()->addSeconds($cooldownSeconds * 2);

        Cache::put($this->failureCountKey($provider), $failureCount, $failureTtl);

        if ($failureCount < $this->failureThreshold()) {
            return;
        }

        $openUntil = now()->addSeconds($cooldownSeconds);

        Cache::put($this->openUntilKey($provider), $openUntil->getTimestamp(), $openUntil);
    }

    protected function failureThreshold(): int
    {
        $configuredThreshold = (int) config('processing.provider_circuit.failure_threshold', 3);

        return $configuredThreshold > 0 ? $configuredThreshold : 3;
    }

    protected function cooldownSeconds(): int
    {
        $configuredCooldown = (int) config('processing.provider_circuit.cooldown_seconds', 60);

        return $configuredCooldown > 0 ? $configuredCooldown : 60;
    }

    protected function failureCountKey(string $provider): string
    {
        return sprintf('processing:provider-circuit:%s:failures', $provider);
    }

    protected function openUntilKey(string $provider): string
    {
        return sprintf('processing:provider-circuit:%s:open-until', $provider);
    }
}

<?php

namespace App\Services\Processing;

use RuntimeException;
use Throwable;

class ProviderDegradedException extends RuntimeException
{
    public function __construct(
        public string $provider,
        public string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Provider [%s] is degraded: %s', $provider, $reason),
            0,
            $previous,
        );
    }

    public static function fromThrowable(string $provider, Throwable $throwable): self
    {
        $reason = trim($throwable->getMessage());

        if ($reason === '') {
            $reason = $throwable::class;
        }

        return new self($provider, $reason, $throwable);
    }
}

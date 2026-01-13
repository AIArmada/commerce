<?php

declare(strict_types=1);

namespace AIArmada\Chip\Exceptions;

use RuntimeException;

class ChipRateLimitException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfter = 60,
        string $message = 'Rate limit exceeded for CHIP API',
    ) {
        parent::__construct($message, 429);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}

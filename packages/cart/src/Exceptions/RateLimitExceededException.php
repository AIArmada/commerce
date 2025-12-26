<?php

declare(strict_types=1);

namespace AIArmada\Cart\Exceptions;

use AIArmada\Cart\Security\CartRateLimitResult;
use RuntimeException;

/**
 * Exception thrown when a cart operation is rate limited.
 */
final class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly CartRateLimitResult $result,
        ?string $message = null
    ) {
        parent::__construct($message ?? $result->getMessage());
    }

    public function getOperation(): string
    {
        return $this->result->operation;
    }

    public function getWindow(): ?string
    {
        return $this->result->window;
    }

    public function getRetryAfter(): ?int
    {
        return $this->result->retryAfter;
    }

    public function getLimit(): ?int
    {
        return $this->result->limit;
    }

    /**
     * Get HTTP headers for rate limit response.
     *
     * @return array<string, string|int>
     */
    public function getHeaders(): array
    {
        $headers = [
            'X-RateLimit-Operation' => $this->result->operation,
        ];

        if ($this->result->retryAfter !== null) {
            $headers['Retry-After'] = $this->result->retryAfter;
            $headers['X-RateLimit-Reset'] = time() + $this->result->retryAfter;
        }

        if ($this->result->limit !== null) {
            $headers['X-RateLimit-Limit'] = $this->result->limit;
        }

        return $headers;
    }
}

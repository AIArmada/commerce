<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security;

/**
 * Result of a rate limit check.
 */
final readonly class CartRateLimitResult
{
    public function __construct(
        public bool $allowed,
        public string $operation,
        public ?string $window = null,
        public ?int $retryAfter = null,
        public ?int $limit = null,
        public int $remainingMinute = -1,
        public int $remainingHour = -1,
    ) {}

    /**
     * Create an allowed result.
     */
    public static function allowed(
        string $operation,
        int $remainingMinute,
        int $remainingHour
    ): self {
        return new self(
            allowed: true,
            operation: $operation,
            remainingMinute: $remainingMinute,
            remainingHour: $remainingHour,
        );
    }

    /**
     * Create an exceeded result.
     */
    public static function exceeded(
        string $operation,
        string $window,
        int $retryAfter,
        int $limit
    ): self {
        return new self(
            allowed: false,
            operation: $operation,
            window: $window,
            retryAfter: $retryAfter,
            limit: $limit,
        );
    }

    /**
     * Get human-readable message for the result.
     */
    public function getMessage(): string
    {
        if ($this->allowed) {
            return "Operation '{$this->operation}' allowed.";
        }

        return "Rate limit exceeded for '{$this->operation}'. "
            . "Limit: {$this->limit} per {$this->window}. "
            . "Retry after {$this->retryAfter} seconds.";
    }

    /**
     * Convert to array for API responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'allowed' => $this->allowed,
            'operation' => $this->operation,
        ];

        if (! $this->allowed) {
            $data['window'] = $this->window;
            $data['retry_after'] = $this->retryAfter;
            $data['limit'] = $this->limit;
        } else {
            $data['remaining'] = [
                'minute' => $this->remainingMinute,
                'hour' => $this->remainingHour,
            ];
        }

        return $data;
    }
}

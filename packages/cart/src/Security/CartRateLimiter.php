<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Tiered rate limiting for cart operations.
 *
 * Provides different rate limits for different operation types,
 * protecting against abuse while allowing normal usage patterns.
 */
final class CartRateLimiter
{
    /**
     * Rate limits by operation type.
     *
     * @var array<string, array{perMinute: int, perHour: int}>
     */
    private array $limits;

    /**
     * Prefix for rate limiter keys.
     */
    private string $keyPrefix;

    /**
     * Whether rate limiting is enabled (short-circuits checks when false).
     */
    private bool $enabled;

    public function __construct(?array $limits = null, string $keyPrefix = 'cart', bool $enabled = true)
    {
        $this->keyPrefix = $keyPrefix;
        $this->limits = $limits ?? $this->getDefaultLimits();
        $this->enabled = $enabled;
    }

    /**
     * Check if an operation is allowed for the given identifier.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $operation  Operation type (add_item, remove_item, etc.)
     * @return CartRateLimitResult Result with allowed status and retry info
     */
    public function check(string $identifier, string $operation): CartRateLimitResult
    {
        if (! $this->enabled) {
            return CartRateLimitResult::allowed(
                operation: $operation,
                remainingMinute: PHP_INT_MAX,
                remainingHour: PHP_INT_MAX
            );
        }

        $config = $this->limits[$operation] ?? $this->limits['default'];

        // Per-minute check
        $minuteKey = $this->buildKey($operation, $identifier, 'minute');
        if (RateLimiter::tooManyAttempts($minuteKey, $config['perMinute'])) {
            return CartRateLimitResult::exceeded(
                operation: $operation,
                window: 'minute',
                retryAfter: RateLimiter::availableIn($minuteKey),
                limit: $config['perMinute']
            );
        }

        // Per-hour check
        $hourKey = $this->buildKey($operation, $identifier, 'hour');
        if (RateLimiter::tooManyAttempts($hourKey, $config['perHour'])) {
            return CartRateLimitResult::exceeded(
                operation: $operation,
                window: 'hour',
                retryAfter: RateLimiter::availableIn($hourKey),
                limit: $config['perHour']
            );
        }

        // Record attempts
        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);

        return CartRateLimitResult::allowed(
            operation: $operation,
            remainingMinute: RateLimiter::remaining($minuteKey, $config['perMinute']),
            remainingHour: RateLimiter::remaining($hourKey, $config['perHour'])
        );
    }

    /**
     * Check multiple operations at once (for batch operations).
     *
     * @param  string  $identifier  User/session identifier
     * @param  array<string>  $operations  List of operations to check
     * @return CartRateLimitResult First exceeded result, or allowed if all pass
     */
    public function checkMultiple(string $identifier, array $operations): CartRateLimitResult
    {
        if (! $this->enabled) {
            return CartRateLimitResult::allowed(
                operation: 'batch',
                remainingMinute: PHP_INT_MAX,
                remainingHour: PHP_INT_MAX
            );
        }

        foreach ($operations as $operation) {
            $result = $this->check($identifier, $operation);

            if (! $result->allowed) {
                return $result;
            }
        }

        return CartRateLimitResult::allowed(
            operation: 'batch',
            remainingMinute: -1,
            remainingHour: -1
        );
    }

    /**
     * Clear rate limit for a specific operation and identifier.
     *
     * Useful for trusted users or after successful verification.
     */
    public function clear(string $identifier, string $operation): void
    {
        if (! $this->enabled) {
            return;
        }

        $minuteKey = $this->buildKey($operation, $identifier, 'minute');
        $hourKey = $this->buildKey($operation, $identifier, 'hour');

        RateLimiter::clear($minuteKey);
        RateLimiter::clear($hourKey);
    }

    /**
     * Clear all rate limits for an identifier.
     */
    public function clearAll(string $identifier): void
    {
        if (! $this->enabled) {
            return;
        }

        foreach (array_keys($this->limits) as $operation) {
            $this->clear($identifier, $operation);
        }
    }

    /**
     * Get remaining attempts for an operation.
     *
     * @return array{minute: int, hour: int}
     */
    public function remaining(string $identifier, string $operation): array
    {
        if (! $this->enabled) {
            return [
                'minute' => PHP_INT_MAX,
                'hour' => PHP_INT_MAX,
            ];
        }

        $config = $this->limits[$operation] ?? $this->limits['default'];

        $minuteKey = $this->buildKey($operation, $identifier, 'minute');
        $hourKey = $this->buildKey($operation, $identifier, 'hour');

        return [
            'minute' => RateLimiter::remaining($minuteKey, $config['perMinute']),
            'hour' => RateLimiter::remaining($hourKey, $config['perHour']),
        ];
    }

    /**
     * Apply a trust multiplier for verified users.
     *
     * Higher multiplier = higher limits (e.g., 2.0 = double limits).
     */
    public function withTrustMultiplier(float $multiplier): self
    {
        $adjustedLimits = [];

        foreach ($this->limits as $operation => $config) {
            $adjustedLimits[$operation] = [
                'perMinute' => (int) ($config['perMinute'] * $multiplier),
                'perHour' => (int) ($config['perHour'] * $multiplier),
            ];
        }

        return new self($adjustedLimits, $this->keyPrefix);
    }

    /**
     * Get configured limits for debugging/monitoring.
     *
     * @return array<string, array{perMinute: int, perHour: int}>
     */
    public function getLimits(): array
    {
        return $this->limits;
    }

    /**
     * Build rate limiter key.
     */
    private function buildKey(string $operation, string $identifier, string $window): string
    {
        return "{$this->keyPrefix}:{$operation}:{$identifier}:{$window}";
    }

    /**
     * Get default rate limits for cart operations.
     *
     * @return array<string, array{perMinute: int, perHour: int}>
     */
    private function getDefaultLimits(): array
    {
        return [
            'add_item' => ['perMinute' => 60, 'perHour' => 500],
            'update_item' => ['perMinute' => 120, 'perHour' => 1000],
            'remove_item' => ['perMinute' => 60, 'perHour' => 500],
            'clear_cart' => ['perMinute' => 10, 'perHour' => 50],
            'checkout' => ['perMinute' => 5, 'perHour' => 20],
            'merge_cart' => ['perMinute' => 5, 'perHour' => 30],
            'get_cart' => ['perMinute' => 300, 'perHour' => 3000],
            'add_condition' => ['perMinute' => 30, 'perHour' => 200],
            'remove_condition' => ['perMinute' => 30, 'perHour' => 200],
            'default' => ['perMinute' => 60, 'perHour' => 500],
        ];
    }
}

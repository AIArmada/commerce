<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Exceptions\RateLimitExceededException;
use AIArmada\Cart\Security\CartRateLimiter;

/**
 * Provides rate limiting capability for cart operations.
 *
 * When enabled via config, cart operations are automatically rate limited
 * to protect against abuse and ensure fair usage.
 */
trait HasRateLimiting
{
    private ?CartRateLimiter $rateLimiter = null;

    private bool $rateLimitingEnabled = true;

    /**
     * Set the rate limiter instance.
     */
    public function setRateLimiter(?CartRateLimiter $rateLimiter): static
    {
        $this->rateLimiter = $rateLimiter;

        return $this;
    }

    /**
     * Get the rate limiter instance.
     */
    public function getRateLimiter(): ?CartRateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Enable or disable rate limiting for this cart instance.
     */
    public function withRateLimiting(bool $enabled = true): static
    {
        $this->rateLimitingEnabled = $enabled;

        return $this;
    }

    /**
     * Disable rate limiting for this cart instance.
     */
    public function withoutRateLimiting(): static
    {
        return $this->withRateLimiting(false);
    }

    /**
     * Check if rate limiting is enabled and configured.
     */
    public function isRateLimitingEnabled(): bool
    {
        return $this->rateLimitingEnabled
            && $this->rateLimiter !== null
            && config('cart.rate_limiting.enabled', true);
    }

    /**
     * Check rate limit for an operation.
     *
     * @throws RateLimitExceededException If rate limit is exceeded
     */
    protected function checkRateLimit(string $operation): void
    {
        if (! $this->isRateLimitingEnabled()) {
            return;
        }

        $identifier = $this->getRateLimitIdentifier();
        $result = $this->rateLimiter->check($identifier, $operation);

        if (! $result->allowed) {
            throw new RateLimitExceededException($result);
        }
    }

    /**
     * Get the identifier used for rate limiting.
     *
     * Uses the cart identifier which typically maps to user ID or session ID.
     */
    protected function getRateLimitIdentifier(): string
    {
        return $this->getIdentifier();
    }
}

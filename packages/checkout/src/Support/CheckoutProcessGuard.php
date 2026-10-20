<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

/**
 * Request-scoped guard tracking checkout sessions currently being processed.
 *
 * Stored as a scoped container binding (not static mutable state) so it
 * cannot leak across Octane requests.
 */
final class CheckoutProcessGuard
{
    /** @var array<string, true> */
    public array $active = [];

    public function mark(string $sessionId): void
    {
        $this->active[$sessionId] = true;
    }

    public function unmark(string $sessionId): void
    {
        unset($this->active[$sessionId]);
    }

    public function isActive(string $sessionId): bool
    {
        return isset($this->active[$sessionId]);
    }

    public function flushState(): void
    {
        $this->active = [];
    }
}

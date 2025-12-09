<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events\Store;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartEvent;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;

/**
 * Service for recording cart events to the event store.
 *
 * This service acts as a facade for recording cart events, handling
 * the coordination between the cart and the event repository.
 */
final class CartEventRecorder
{
    private bool $enabled = true;

    public function __construct(
        private readonly CartEventRepositoryInterface $repository
    ) {}

    /**
     * Record a cart event.
     *
     * @param  CartEventInterface  $event  The event to record
     * @param  Cart  $cart  The cart instance
     * @return string|null The stored event UUID, or null if recording is disabled
     */
    public function record(CartEventInterface $event, Cart $cart): ?string
    {
        if (! $this->enabled) {
            return null;
        }

        if (! $event->shouldPersist()) {
            return null;
        }

        $cartId = $cart->getId();

        if ($cartId === null) {
            return null;
        }

        return $this->repository->record($event, $cartId);
    }

    /**
     * Record multiple events atomically.
     *
     * @param  array<CartEventInterface>  $events  Events to record
     * @param  Cart  $cart  The cart instance
     * @return array<string> Array of stored event UUIDs
     */
    public function recordBatch(array $events, Cart $cart): array
    {
        if (! $this->enabled) {
            return [];
        }

        $cartId = $cart->getId();

        if ($cartId === null) {
            return [];
        }

        $persistableEvents = array_filter(
            $events,
            static fn (CartEventInterface $event): bool => $event->shouldPersist()
        );

        if (empty($persistableEvents)) {
            return [];
        }

        return $this->repository->recordBatch($persistableEvents, $cartId);
    }

    /**
     * Get the event history for a cart.
     *
     * @param  Cart  $cart  The cart instance
     * @param  int  $fromPosition  Start from this stream position (exclusive)
     * @return array<CartEvent>
     */
    public function getHistory(Cart $cart, int $fromPosition = 0): array
    {
        $cartId = $cart->getId();

        if ($cartId === null) {
            return [];
        }

        return $this->repository->getEventsForCart($cartId, $fromPosition);
    }

    /**
     * Get events of a specific type for a cart.
     *
     * @param  Cart  $cart  The cart instance
     * @param  string  $eventType  Event type to filter
     * @return array<CartEvent>
     */
    public function getEventsByType(Cart $cart, string $eventType): array
    {
        $cartId = $cart->getId();

        if ($cartId === null) {
            return [];
        }

        return $this->repository->getEventsByType($cartId, $eventType);
    }

    /**
     * Get the number of events for a cart.
     *
     * @param  Cart  $cart  The cart instance
     */
    public function getEventCount(Cart $cart): int
    {
        $cartId = $cart->getId();

        if ($cartId === null) {
            return 0;
        }

        return $this->repository->getEventCount($cartId);
    }

    /**
     * Enable event recording.
     */
    public function enable(): static
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable event recording.
     */
    public function disable(): static
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Check if event recording is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Execute a callback with recording disabled.
     *
     * @param  callable  $callback  Callback to execute
     * @return mixed The callback's return value
     */
    public function withoutRecording(callable $callback): mixed
    {
        $wasEnabled = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $wasEnabled;
        }
    }
}

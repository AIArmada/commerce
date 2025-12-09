<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events\Store;

use AIArmada\Cart\Models\CartEvent;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;

/**
 * Interface for cart event repository implementations.
 *
 * Provides a contract for storing and retrieving cart events
 * for event sourcing, audit trails, and replay functionality.
 */
interface CartEventRepositoryInterface
{
    /**
     * Record a cart event to the store.
     *
     * @param  CartEventInterface  $event  The event to record
     * @param  string  $cartId  The cart UUID
     * @return string The stored event's UUID
     */
    public function record(CartEventInterface $event, string $cartId): string;

    /**
     * Record multiple events atomically.
     *
     * @param  array<CartEventInterface>  $events  Events to record
     * @param  string  $cartId  The cart UUID
     * @return array<string> Array of stored event UUIDs
     */
    public function recordBatch(array $events, string $cartId): array;

    /**
     * Get all events for a cart in stream order.
     *
     * @param  string  $cartId  The cart UUID
     * @param  int  $fromPosition  Start from this stream position (exclusive)
     * @return array<CartEvent>
     */
    public function getEventsForCart(string $cartId, int $fromPosition = 0): array;

    /**
     * Get events for a cart filtered by type.
     *
     * @param  string  $cartId  The cart UUID
     * @param  string  $eventType  Event type to filter
     * @return array<CartEvent>
     */
    public function getEventsByType(string $cartId, string $eventType): array;

    /**
     * Get the latest stream position for a cart.
     *
     * @param  string  $cartId  The cart UUID
     * @return int Latest stream position (0 if no events)
     */
    public function getLatestPosition(string $cartId): int;

    /**
     * Get the latest aggregate version for a cart.
     *
     * @param  string  $cartId  The cart UUID
     * @return int Latest aggregate version (0 if no events)
     */
    public function getLatestVersion(string $cartId): int;

    /**
     * Get event count for a cart.
     *
     * @param  string  $cartId  The cart UUID
     */
    public function getEventCount(string $cartId): int;

    /**
     * Delete all events for a cart (for cleanup).
     *
     * Use with caution - this destroys audit history.
     *
     * @param  string  $cartId  The cart UUID
     * @return int Number of events deleted
     */
    public function deleteEventsForCart(string $cartId): int;
}

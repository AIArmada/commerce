<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Contracts\Events;

/**
 * Interface for cart-specific events.
 *
 * Extends the base commerce event interface with cart-specific methods
 * for event sourcing, replay, and analytics.
 */
interface CartEventInterface extends CommerceEventInterface
{
    /**
     * Get the cart identifier this event belongs to.
     *
     * @return string Cart identifier (user ID, session ID, etc.)
     */
    public function getCartIdentifier(): string;

    /**
     * Get the cart instance name.
     *
     * @return string Instance name (e.g., 'default', 'wishlist', 'saved-for-later')
     */
    public function getCartInstance(): string;

    /**
     * Get the cart ID (UUID) if available.
     *
     * @return string|null Cart primary key UUID
     */
    public function getCartId(): ?string;

    /**
     * Get the aggregate version at the time of this event.
     *
     * Used for event sourcing and optimistic concurrency control.
     *
     * @return int Aggregate version number
     */
    public function getAggregateVersion(): int;

    /**
     * Check if this event should be persisted to the event store.
     *
     * Some events may be transient and not need persistence.
     *
     * @return bool True if event should be stored
     */
    public function shouldPersist(): bool;
}

<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\Concerns\HasCartEventData;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when all metadata is cleared from the cart
 *
 * This event is fired whenever all metadata is deleted from the cart,
 * useful for tracking bulk metadata changes and cleanup operations.
 *
 * @since 2.0.0
 */
final class MetadataCleared implements CartEventInterface
{
    use Dispatchable, HasCartEventData, InteractsWithSockets, SerializesModels;

    /**
     * Create a new metadata cleared event
     *
     * @param  Cart  $cart  The cart instance
     */
    public function __construct(
        public readonly Cart $cart,
    ) {
        $this->initializeEventData();
    }

    /**
     * Get the event type identifier.
     */
    public function getEventType(): string
    {
        return 'cart.metadata.cleared';
    }

    /**
     * Get the cart identifier this event belongs to.
     */
    public function getCartIdentifier(): string
    {
        return $this->cart->getIdentifier();
    }

    /**
     * Get the cart instance name.
     */
    public function getCartInstance(): string
    {
        return $this->cart->instance();
    }

    /**
     * Get the cart ID (UUID) if available.
     */
    public function getCartId(): ?string
    {
        return $this->cart->getId();
    }

    /**
     * Get event data for broadcasting or logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cart' => [
                'identifier' => $this->cart->getIdentifier(),
                'instance' => $this->cart->instance(),
                'items_count' => $this->cart->countItems(),
                'total' => $this->cart->getRawTotal(),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

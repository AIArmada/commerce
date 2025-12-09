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
 * Event fired when a cart is cleared of all items, conditions, and metadata.
 *
 * This event is dispatched when all content is removed from the cart,
 * effectively resetting it to an empty state while maintaining the cart structure in storage.
 * The cart entity itself remains and can be refilled with new items.
 *
 * This is different from CartDestroyed which completely removes the cart from storage.
 *
 * @example
 * ```php
 * CartCleared::dispatch($cart);
 *
 * // Listen for cart clearing
 * Event::listen(CartCleared::class, function (CartCleared $event) {
 *     logger('Cart cleared', ['identifier' => $event->cart->getIdentifier()]);
 * });
 * ```
 */
final class CartCleared implements CartEventInterface
{
    use Dispatchable;
    use HasCartEventData;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new cart cleared event instance.
     *
     * @param  Cart  $cart  The cart instance that was cleared
     */
    public function __construct(
        public readonly Cart $cart
    ) {
        $this->initializeEventData();
    }

    /**
     * Get the event type identifier.
     */
    public function getEventType(): string
    {
        return 'cart.cleared';
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
     * Get the event data as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->cart->getIdentifier(),
            'instance_name' => $this->cart->instance(),
            'timestamp' => now()->toISOString(),
        ];
    }
}

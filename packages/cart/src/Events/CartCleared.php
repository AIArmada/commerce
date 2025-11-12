<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
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
final class CartCleared
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new cart cleared event instance.
     *
     * @param  Cart  $cart  The cart instance that was cleared
     */
    public function __construct(
        public readonly Cart $cart
    ) {
        //
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

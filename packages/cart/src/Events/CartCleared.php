<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a cart is cleared of all items and conditions.
 *
 * This event is dispatched whenever all items and conditions are removed from the cart,
 * effectively resetting it to an empty state while maintaining the cart instance.
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

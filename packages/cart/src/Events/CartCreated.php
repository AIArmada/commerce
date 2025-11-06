<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a new cart is created.
 *
 * This event is dispatched whenever a cart instance is created for the first time,
 * typically when a user first interacts with the shopping cart functionality.
 *
 * @example
 * ```php
 * CartCreated::dispatch($cart);
 *
 * // Listen for cart creation
 * Event::listen(CartCreated::class, function (CartCreated $event) {
 *     logger('New cart created', ['session_id' => $event->cart->getSessionId()]);
 * });
 * ```
 */
final class CartCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new cart created event instance.
     *
     * @param  Cart  $cart  The cart instance that was created
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

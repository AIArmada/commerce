<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an item is removed from the cart.
 *
 * This event is dispatched whenever an item is completely removed from the cart
 * or when an item's quantity is reduced to zero.
 *
 * @example
 * ```php
 * ItemRemoved::dispatch($item, $cart);
 *
 * // Listen for item removals
 * Event::listen(ItemRemoved::class, function (ItemRemoved $event) {
 *     logger('Item removed from cart', [
 *         'item_id' => $event->item->id,
 *         'quantity' => $event->item->quantity,
 *         'cart_identifier' => $event->cart->getIdentifier(),
 *     ]);
 * });
 * ```
 */
final class ItemRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new item removed event instance.
     *
     * @param  CartItem  $item  The item that was removed from the cart
     * @param  Cart  $cart  The cart instance where the item was removed from
     */
    public function __construct(
        public readonly CartItem $item,
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
            'item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'quantity' => $this->item->quantity,
            'price' => $this->item->price,
            'identifier' => $this->cart->getIdentifier(),
            'instance_name' => $this->cart->instance(),
            'timestamp' => now()->toISOString(),
        ];
    }
}

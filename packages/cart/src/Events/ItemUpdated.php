<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an item in the cart is updated.
 *
 * This event is dispatched whenever an existing item's properties (such as quantity,
 * price, or attributes) are modified without removing or adding the item.
 *
 * @example
 * ```php
 * ItemUpdated::dispatch($item, $cart);
 *
 * // Listen for item updates
 * Event::listen(ItemUpdated::class, function (ItemUpdated $event) {
 *     logger('Item updated in cart', [
 *         'item_id' => $event->item->id,
 *         'quantity' => $event->item->quantity,
 *         'cart_identifier' => $event->cart->getIdentifier(),
 *     ]);
 * });
 * ```
 */
final class ItemUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new item updated event instance.
     *
     * @param  CartItem  $item  The item that was updated in the cart
     * @param  Cart  $cart  The cart instance where the item was updated
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

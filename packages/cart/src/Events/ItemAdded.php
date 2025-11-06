<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an item is added to the cart.
 *
 * This event is dispatched whenever a new item is added to the cart or when
 * an existing item's quantity is increased.
 *
 * @example
 * ```php
 * ItemAdded::dispatch($item, $cart);
 *
 * // Listen for item additions
 * Event::listen(ItemAdded::class, function (ItemAdded $event) {
 *     logger('Item added to cart', [
 *         'item_id' => $event->item->id,
 *         'quantity' => $event->item->quantity,
 *         'cart_identifier' => $event->cart->getIdentifier(),
 *     ]);
 * });
 * ```
 */
final class ItemAdded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new item added event instance.
     *
     * @param  CartItem  $item  The item that was added to the cart
     * @param  Cart  $cart  The cart instance where the item was added
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

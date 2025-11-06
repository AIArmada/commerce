<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when metadata is removed from the cart
 *
 * This event is fired whenever metadata is deleted from the cart,
 * useful for tracking custom data changes and cleanup operations.
 *
 * @since 2.0.0
 */
final class MetadataRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new metadata removed event
     *
     * @param  string  $key  The metadata key that was removed
     * @param  Cart  $cart  The cart instance
     */
    public function __construct(
        public readonly string $key,
        public readonly Cart $cart,
    ) {
        //
    }

    /**
     * Get event data for broadcasting or logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
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

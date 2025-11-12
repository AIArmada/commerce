<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a cart is completely destroyed and removed from storage.
 *
 * This event is dispatched when a cart is permanently deleted from storage.
 * Unlike CartCleared which empties the cart but keeps the structure,
 * CartDestroyed indicates the cart entity itself has been removed.
 *
 * @example
 * ```php
 * CartDestroyed::dispatch($identifier, $instance);
 *
 * // Listen for cart destruction
 * Event::listen(CartDestroyed::class, function (CartDestroyed $event) {
 *     logger('Cart destroyed', [
 *         'identifier' => $event->identifier,
 *         'instance' => $event->instance
 *     ]);
 * });
 * ```
 */
final class CartDestroyed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new cart destroyed event instance.
     *
     * @param  string  $identifier  The cart identifier that was destroyed
     * @param  string  $instance  The cart instance name that was destroyed
     */
    public function __construct(
        public readonly string $identifier,
        public readonly string $instance
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
            'identifier' => $this->identifier,
            'instance_name' => $this->instance,
            'timestamp' => now()->toISOString(),
        ];
    }
}

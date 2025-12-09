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
final class CartCreated implements CartEventInterface
{
    use Dispatchable;
    use HasCartEventData;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new cart created event instance.
     *
     * @param  Cart  $cart  The cart instance that was created
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
        return 'cart.created';
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

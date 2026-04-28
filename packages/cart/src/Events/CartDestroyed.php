<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Events\Concerns\HasCartEventData;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;
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
 * CartDestroyed::dispatch($identifier, $instance, $cartId, $owner_type, $owner_id);
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
final class CartDestroyed implements CartEventInterface
{
    use Dispatchable;
    use HasCartEventData;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new cart destroyed event instance.
     *
     * @param  string  $identifier  The cart identifier that was destroyed
     * @param  string  $instance  The cart instance name that was destroyed
     * @param  string|null  $cartId  The cart UUID (captured before destruction)
    * @param  string|null  $owner_type  The owner morph class for the destroyed cart scope
    * @param  string|int|null  $owner_id  The owner key for the destroyed cart scope
     */
    public function __construct(
        public readonly string $identifier,
        public readonly string $instance,
        public readonly ?string $cartId = null,
        public readonly ?string $owner_type = null,
        public readonly string | int | null $owner_id = null,
    ) {
        $this->initializeEventData();
    }

    /**
     * Get the event type identifier.
     */
    public function getEventType(): string
    {
        return 'cart.destroyed';
    }

    /**
     * Get the cart identifier this event belongs to.
     */
    public function getCartIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get the cart instance name.
     */
    public function getCartInstance(): string
    {
        return $this->instance;
    }

    /**
     * Get the cart ID (UUID) if available.
     */
    public function getCartId(): ?string
    {
        return $this->cartId;
    }

    public function getOwnerType(): ?string
    {
        return $this->owner_type;
    }

    public function getOwnerId(): string | int | null
    {
        return $this->owner_id;
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
            'cart_id' => $this->cartId,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id !== null ? (string) $this->owner_id : null,
            'timestamp' => now()->toISOString(),
        ];
    }
}

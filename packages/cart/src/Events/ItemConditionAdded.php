<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Events\Concerns\HasCartEventData;
use AIArmada\CommerceSupport\Contracts\Events\CartEventInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a condition is added to a specific cart item
 *
 * This event is fired whenever a new condition (discount, tax, fee, etc.)
 * is added to a specific item in the cart. Useful for tracking item-level
 * promotions, analytics, and triggering related business logic.
 *
 * @since 2.0.0
 */
final class ItemConditionAdded implements CartEventInterface
{
    use Dispatchable, HasCartEventData, InteractsWithSockets, SerializesModels;

    /**
     * Create a new item condition added event
     *
     * @param  CartCondition  $condition  The condition that was added to the item
     * @param  Cart  $cart  The cart instance containing the item
     * @param  string  $itemId  The ID of the item the condition was added to
     */
    public function __construct(
        public readonly CartCondition $condition,
        public readonly Cart $cart,
        public readonly string $itemId,
    ) {
        $this->initializeEventData();
    }

    /**
     * Get the event type identifier.
     */
    public function getEventType(): string
    {
        return 'cart.item.condition.added';
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
     * Get the condition's calculated impact on the item
     *
     * @return float The monetary impact of the condition (positive for charges, negative for discounts)
     */
    public function getConditionImpact(): float
    {
        $item = $this->cart->get($this->itemId);
        $baseValue = $item?->getRawSubtotal() ?? 0;

        return $this->condition->getCalculatedValue($baseValue);
    }

    /**
     * Get event data for broadcasting or logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $item = $this->cart->get($this->itemId);

        return [
            'condition' => [
                'name' => $this->condition->getName(),
                'type' => $this->condition->getType(),
                'value' => $this->condition->getValue(),
                'target_definition' => $this->condition->getTargetDefinition()->toArray(),
            ],
            'item' => [
                'id' => $this->itemId,
                'name' => $item?->name,
                'quantity' => $item?->quantity,
                'subtotal' => $item?->getRawSubtotal(),
            ],
            'cart' => [
                'instance' => $this->cart->instance(),
                'items_count' => $this->cart->countItems(),
                'total_quantity' => $this->cart->getTotalQuantity(),
                'subtotal' => $this->cart->getRawSubtotal(),
                'total' => $this->cart->getRawTotal(),
            ],
            'impact' => $this->getConditionImpact(),
            'timestamp' => now()->toISOString(),
        ];
    }
}

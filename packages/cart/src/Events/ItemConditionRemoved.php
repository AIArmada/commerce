<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a condition is removed from a specific cart item
 *
 * This event is fired whenever a condition (discount, tax, fee, etc.)
 * is removed from a specific item in the cart. Useful for tracking item-level
 * promotion removals, analytics, and triggering related business logic.
 *
 * @since 2.0.0
 */
final class ItemConditionRemoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new item condition removed event
     *
     * @param  CartCondition  $condition  The condition that was removed from the item
     * @param  Cart  $cart  The cart instance containing the item
     * @param  string  $itemId  The ID of the item the condition was removed from
     * @param  string|null  $reason  Optional reason for removal ('expired', 'user_action', 'system', etc.)
     */
    public function __construct(
        public readonly CartCondition $condition,
        public readonly Cart $cart,
        public readonly string $itemId,
        public readonly ?string $reason = null
    ) {
        //
    }

    /**
     * Get the condition's former impact on the item
     *
     * @return float The monetary impact the condition had (positive for charges, negative for discounts)
     */
    public function getConditionImpact(): float
    {
        $item = $this->cart->get($this->itemId);
        $baseValue = $item?->getRawSubtotal() ?? 0;

        return $this->condition->getCalculatedValue($baseValue);
    }

    /**
     * Get the savings that were lost (for discounts)
     *
     * @return float Positive amount representing lost savings, 0 for non-discounts
     */
    public function getLostSavings(): float
    {
        return $this->condition->isDiscount() ? abs($this->getConditionImpact()) : 0;
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
            'lost_savings' => $this->getLostSavings(),
            'reason' => $this->reason,
            'timestamp' => now()->toISOString(),
        ];
    }
}

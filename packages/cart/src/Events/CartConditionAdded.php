<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a condition is added to the cart level
 *
 * This event is fired whenever a new condition (discount, tax, fee, etc.)
 * is added to the entire cart. Useful for tracking promotional usage, analytics,
 * and triggering related business logic.
 *
 * @since 2.0.0
 */
final class CartConditionAdded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new cart condition added event
     *
     * @param  CartCondition  $condition  The condition that was added to the cart
     * @param  Cart  $cart  The cart instance the condition was added to
     */
    public function __construct(
        public readonly CartCondition $condition,
        public readonly Cart $cart,
    ) {
        //
    }

    /**
     * Get the condition's calculated impact on the cart
     *
     * @return float The monetary impact of the condition (positive for charges, negative for discounts)
     */
    public function getConditionImpact(): float
    {
        return $this->condition->getCalculatedValue($this->cart->getRawSubtotal());
    }

    /**
     * Get event data for broadcasting or logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'condition' => [
                'name' => $this->condition->getName(),
                'type' => $this->condition->getType(),
                'value' => $this->condition->getValue(),
                'target' => $this->condition->getTarget(),
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

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
 * Event dispatched when a condition is removed from the cart level
 *
 * This event is fired whenever a condition (discount, tax, fee, etc.)
 * is removed from the entire cart. Useful for tracking promotional code removals,
 * analytics, and triggering related business logic.
 *
 * @since 2.0.0
 */
final class CartConditionRemoved implements CartEventInterface
{
    use Dispatchable;
    use HasCartEventData;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new cart condition removed event
     *
     * @param  CartCondition  $condition  The condition that was removed from the cart
     * @param  Cart  $cart  The cart instance the condition was removed from
     * @param  string|null  $reason  Optional reason for removal ('expired', 'user_action', 'system', etc.)
     */
    public function __construct(
        public readonly CartCondition $condition,
        public readonly Cart $cart,
        public readonly ?string $reason = null
    ) {
        $this->initializeEventData();
    }

    /**
     * Get the event type identifier.
     */
    public function getEventType(): string
    {
        return 'cart.condition.removed';
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
     * Get the condition's former impact on the cart
     *
     * @return float The monetary impact the condition had (positive for charges, negative for discounts)
     */
    public function getConditionImpact(): float
    {
        return $this->condition->getCalculatedValue($this->cart->getRawSubtotal());
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
        return [
            'condition' => [
                'name' => $this->condition->getName(),
                'type' => $this->condition->getType(),
                'value' => $this->condition->getValue(),
                'target_definition' => $this->condition->getTargetDefinition()->toArray(),
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

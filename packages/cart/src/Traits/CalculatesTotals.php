<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Models\CartItem;
use Akaunting\Money\Money;

trait CalculatesTotals
{
    /**
     * Get cart subtotal (with item-level and subtotal-targeted conditions applied) - returns Money object for chaining
     */
    public function subtotal(): Money
    {
        return $this->getSubtotal();
    }

    /**
     * Get cart subtotal without any conditions (raw base prices) - returns Money object for chaining
     */
    public function subtotalWithoutConditions(): Money
    {
        return $this->getSubtotalWithoutConditions();
    }

    /**
     * Get cart total with all conditions applied (item + subtotal + total-level) - returns Money object for chaining
     */
    public function total(): Money
    {
        return $this->getTotal();
    }

    /**
     * Get cart total without any conditions (raw base prices) - returns Money object for chaining
     */
    public function totalWithoutConditions(): Money
    {
        return $this->getSubtotalWithoutConditions();
    }

    /**
     * Get savings (subtotal without conditions - total) - returns Money object for chaining
     */
    public function savings(): Money
    {
        $withoutConditions = $this->getSubtotalWithoutConditions();
        $withConditions = $this->getTotal();

        $savings = $withoutConditions->subtract($withConditions);

        $currency = config('cart.money.default_currency', 'USD');

        // Return zero if savings would be negative
        return $savings->isNegative() || $savings->isZero() ? Money::{$currency}(0) : $savings;
    }

    /**
     * Get total quantity of all items
     */
    public function getTotalQuantity(): int
    {
        return $this->getItems()->sum('quantity');
    }

    /**
     * Get count of unique items in cart
     */
    public function countItems(): int
    {
        return $this->getItems()->count();
    }

    /**
     * Get raw total in cents (for internal use in events and storage serialization)
     */
    public function getRawTotal(): int
    {
        $pipelineResult = $this->evaluateConditionPipeline();

        return $pipelineResult->total();
    }

    /**
     * Get raw subtotal in cents (for internal use in events and storage serialization)
     */
    public function getRawSubtotal(): int
    {
        $pipelineResult = $this->evaluateConditionPipeline();

        return $pipelineResult->subtotal();
    }

    /**
     * Get raw cart subtotal in cents without any conditions (for internal use like events)
     */
    public function getRawSubtotalWithoutConditions(): int
    {
        return (int) $this->getItems()->sum(fn (CartItem $item) => $item->getRawSubtotalWithoutConditions());
    }

    /**
     * Get raw total in cents without any conditions (for internal use in rule evaluation)
     * This prevents circular dependency when dynamic conditions need to check the total
     */
    public function getRawTotalWithoutConditions(): int
    {
        return $this->getRawSubtotalWithoutConditions();
    }

    /**
     * Count items in cart (total quantity, shopping-cart style)
     */
    public function count(): int
    {
        return $this->getTotalQuantity();
    }

    /**
     * Get cart subtotal with item-level conditions and subtotal-targeted cart conditions applied
     */
    protected function getSubtotal(): Money
    {
        $pipelineResult = $this->evaluateConditionPipeline();
        $finalAmount = $pipelineResult->subtotal();

        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($finalAmount);
    }

    /**
     * Get cart subtotal without any conditions
     */
    protected function getSubtotalWithoutConditions(): Money
    {
        $totalAmount = (int) $this->getItems()->sum(fn (CartItem $item) => $item->getRawSubtotalWithoutConditions());
        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($totalAmount);
    }

    /**
     * Get cart total without any conditions (for rule evaluation)
     */
    protected function getTotalWithoutConditions(): Money
    {
        // Same as subtotal without conditions since we're not applying any conditions
        $totalAmount = (int) $this->getItems()->sum(fn (CartItem $item) => $item->getRawSubtotalWithoutConditions());
        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($totalAmount);
    }

    /**
     * Get cart total with all conditions applied
     */
    protected function getTotal(): Money
    {
        $pipelineResult = $this->evaluateConditionPipeline();
        $finalAmount = $pipelineResult->total();

        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($finalAmount);
    }
}

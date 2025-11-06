<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models\Traits;

use Akaunting\Money\Money;

trait MoneyTrait
{
    /**
     * Get raw price value - returns price with conditions applied
     */
    public function getRawPrice(): float|int
    {
        $price = $this->price;
        foreach ($this->conditions as $condition) {
            $price = $condition->apply($price);
        }
        $result = max(0, $price);

        // Preserve original input type behavior - if original was float, keep as float
        if (is_float($this->price) || $result !== (int) $result) {
            return (float) $result;
        }

        return (int) $result;
    }

    /**
     * Get raw price without conditions - returns original numeric value
     */
    public function getRawPriceWithoutConditions(): float|int
    {
        // Return the same type as the original input
        return $this->price;
    }

    /**
     * Get raw subtotal (price × quantity) - returns subtotal with conditions applied
     */
    public function getRawSubtotal(): float|int
    {
        $result = $this->getRawPrice() * $this->quantity;

        // If any part is float or result has decimals, return float
        if (is_float($this->getRawPrice()) || $result !== (int) $result) {
            return (float) $result;
        }

        return (int) $result;
    }

    /**
     * Get raw subtotal without conditions applied - returns original numeric values
     */
    public function getRawSubtotalWithoutConditions(): float|int
    {
        $result = $this->price * $this->quantity;

        // If any part is float or result has decimals, return float
        if (is_float($this->price) || $result !== (int) $result) {
            return (float) $result;
        }

        return (int) $result;
    }

    /**
     * Get price as Laravel Money object - with conditions applied
     */
    public function getPrice(): Money
    {
        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($this->getRawPrice());
    }

    /**
     * Get subtotal as Laravel Money object - with conditions applied
     */
    public function getSubtotal(): Money
    {
        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($this->getRawSubtotal());
    }

    /**
     * Calculate discount amount as Money object
     */
    public function getDiscountAmount(): Money
    {
        $originalTotal = $this->getRawSubtotalWithoutConditions();
        $discountedTotal = $this->getRawSubtotal();
        $discountAmount = max(0, $originalTotal - $discountedTotal);

        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($discountAmount);
    }

    /**
     * Get price without conditions as Money object
     */
    public function getPriceWithoutConditions(): Money
    {
        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($this->price);
    }

    /**
     * Get subtotal without conditions as Money object
     */
    public function getSubtotalWithoutConditions(): Money
    {
        $currency = config('cart.money.default_currency', 'USD');

        return Money::{$currency}($this->getRawSubtotalWithoutConditions());
    }

    /**
     * Alias for getSubtotal()
     */
    public function subtotal(): Money
    {
        return $this->getSubtotal();
    }

    /**
     * Alias for getSubtotal()
     */
    public function total(): Money
    {
        return $this->getSubtotal();
    }

    /**
     * Alias for getDiscountAmount()
     */
    public function discountAmount(): Money
    {
        return $this->getDiscountAmount();
    }
}

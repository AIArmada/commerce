<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models\Traits;

use Akaunting\Money\Money;

/**
 * Provides Money handling for CartItem.
 *
 * This trait implements LineItemInterface methods along with additional
 * money-related calculations for cart items.
 */
trait MoneyTrait
{
    // =========================================================================
    // LineItemInterface Implementation
    // =========================================================================

    /**
     * Get line item unique identifier.
     */
    public function getLineItemId(): string
    {
        return $this->id;
    }

    /**
     * Get line item name for payment gateway.
     */
    public function getLineItemName(): string
    {
        return $this->name;
    }

    /**
     * Get line item description for payment gateway.
     */
    public function getLineItemDescription(): ?string
    {
        return $this->attributes->get('description');
    }

    /**
     * Get line item price (unit price with conditions applied).
     */
    public function getLineItemPrice(): Money
    {
        return $this->getPrice();
    }

    /**
     * Get line item quantity.
     */
    public function getLineItemQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Get line item total (price × quantity with conditions applied).
     */
    public function getLineItemTotal(): Money
    {
        return $this->getSubtotal();
    }

    /**
     * Get line item discount amount.
     */
    public function getLineItemDiscount(): Money
    {
        return $this->getDiscountAmount();
    }

    /**
     * Get line item tax percentage.
     * Returns 0.0 if no tax is applied at item level.
     */
    public function getLineItemTaxPercent(): float
    {
        // Check conditions for tax percentage
        foreach ($this->conditions as $condition) {
            if ($condition->getType() === 'tax') {
                $value = $condition->getValue();
                // Parse percentage value (e.g., "6%", "+6%")
                if (is_string($value) && str_contains($value, '%')) {
                    return (float) preg_replace('/[^0-9.\-]/', '', $value);
                }
            }
        }

        // Check attributes for tax_percent
        $taxPercent = $this->attributes->get('tax_percent') ?? $this->attributes->get('taxPercent');

        return $taxPercent !== null ? (float) $taxPercent : 0.0;
    }

    /**
     * Get line item subtotal (price × quantity - discount).
     */
    public function getLineItemSubtotal(): Money
    {
        return $this->getSubtotal();
    }

    /**
     * Get line item SKU or identifier.
     */
    public function getLineItemSku(): ?string
    {
        return $this->attributes->get('sku') ?? $this->id;
    }

    /**
     * Get line item category for payment gateway.
     */
    public function getLineItemCategory(): ?string
    {
        return $this->attributes->get('category');
    }

    /**
     * Get line item image URL for payment gateway.
     */
    public function getLineItemImageUrl(): ?string
    {
        return $this->attributes->get('image_url') ?? $this->attributes->get('image');
    }

    /**
     * Get line item metadata for payment gateway.
     *
     * @return array<string, mixed>
     */
    public function getLineItemMetadata(): array
    {
        return [
            'cart_item_id' => $this->id,
            'attributes' => $this->attributes->toArray(),
        ];
    }

    // =========================================================================
    // Raw Price Methods (internal use)
    // =========================================================================

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

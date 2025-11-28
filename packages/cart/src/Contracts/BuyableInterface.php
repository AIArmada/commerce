<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

use Akaunting\Money\Money;

/**
 * Interface for products that can be added to the cart.
 *
 * Implementing this interface allows your Product, Service, or other
 * purchasable models to integrate seamlessly with the cart system.
 *
 * @example
 * ```php
 * class Product extends Model implements BuyableInterface
 * {
 *     public function getBuyableIdentifier(): string
 *     {
 *         return (string) $this->id;
 *     }
 *
 *     public function getBuyableName(): string
 *     {
 *         return $this->name;
 *     }
 *
 *     public function getBuyablePrice(): Money
 *     {
 *         return Money::MYR($this->price);
 *     }
 *
 *     public function canBePurchased(?int $quantity = null): bool
 *     {
 *         if (!$this->is_active) {
 *             return false;
 *         }
 *
 *         if ($this->tracks_inventory && $quantity !== null) {
 *             return $this->stock >= $quantity;
 *         }
 *
 *         return true;
 *     }
 * }
 * ```
 */
interface BuyableInterface
{
    /**
     * Get the unique identifier for the buyable item.
     *
     * This will be used as the cart item ID.
     */
    public function getBuyableIdentifier(): string;

    /**
     * Get the display name for the buyable item.
     */
    public function getBuyableName(): string;

    /**
     * Get the price as a Money object.
     *
     * This should return the unit price (not multiplied by quantity).
     */
    public function getBuyablePrice(): Money;

    /**
     * Check if this item can be purchased.
     *
     * @param  int|null  $quantity  Optional quantity to check against stock
     * @return bool True if the item can be added to cart
     */
    public function canBePurchased(?int $quantity = null): bool;

    /**
     * Get optional attributes to store with the cart item.
     *
     * @return array<string, mixed>
     */
    public function getBuyableAttributes(): array;

    /**
     * Get the buyable item description (for receipts/invoices).
     */
    public function getBuyableDescription(): ?string;

    /**
     * Get the SKU or product code.
     */
    public function getBuyableSku(): ?string;

    /**
     * Get the available stock quantity.
     *
     * Returns null if inventory tracking is disabled.
     */
    public function getBuyableStock(): ?int;

    /**
     * Get the weight in grams (for shipping calculations).
     *
     * Returns null if weight is not applicable.
     */
    public function getBuyableWeight(): ?int;

    /**
     * Get the dimensions in millimeters (for shipping calculations).
     *
     * @return array{length: int, width: int, height: int}|null
     */
    public function getBuyableDimensions(): ?array;

    /**
     * Get the minimum purchase quantity.
     */
    public function getMinimumQuantity(): int;

    /**
     * Get the maximum purchase quantity.
     *
     * Returns null if no maximum.
     */
    public function getMaximumQuantity(): ?int;

    /**
     * Get the quantity increment (e.g., 6 for half-dozen only).
     */
    public function getQuantityIncrement(): int;

    /**
     * Check if this item is taxable.
     */
    public function isTaxable(): bool;

    /**
     * Get the tax category/class for tax calculations.
     */
    public function getTaxCategory(): ?string;
}

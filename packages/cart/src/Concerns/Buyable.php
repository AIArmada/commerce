<?php

declare(strict_types=1);

namespace AIArmada\Cart\Concerns;

use AIArmada\Cart\Contracts\BuyableInterface;
use Akaunting\Money\Money;

/**
 * Trait to implement BuyableInterface for Eloquent models.
 *
 * @example
 * ```php
 * class Product extends Model implements BuyableInterface
 * {
 *     use Buyable;
 *
 *     // Override methods as needed:
 *     public function getBuyablePrice(): Money
 *     {
 *         return Money::MYR($this->sale_price ?? $this->price);
 *     }
 * }
 * ```
 *
 * @property int|string $id
 * @property string $name
 * @property int $price Price in cents
 * @property bool $is_active
 * @property bool $tracks_inventory
 * @property int|null $stock
 * @property int|null $weight Weight in grams
 * @property string|null $sku
 * @property string|null $description
 * @property string|null $tax_category
 */
trait Buyable
{
    /**
     * Get the unique identifier for the buyable item.
     */
    public function getBuyableIdentifier(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Get the display name for the buyable item.
     */
    public function getBuyableName(): string
    {
        return $this->name ?? $this->title ?? 'Unnamed Product';
    }

    /**
     * Get the price as a Money object.
     */
    public function getBuyablePrice(): Money
    {
        $currency = config('cart.money.default_currency', 'MYR');
        $price = $this->price ?? 0;

        return Money::{$currency}($price);
    }

    /**
     * Check if this item can be purchased.
     */
    public function canBePurchased(?int $quantity = null): bool
    {
        // Check if product is active
        if (property_exists($this, 'is_active') && ! $this->is_active) {
            return false;
        }

        // Check stock if tracking inventory
        if ($quantity !== null && $this->tracksInventory()) {
            $stock = $this->getBuyableStock();

            return $stock !== null && $stock >= $quantity;
        }

        return true;
    }

    /**
     * Get optional attributes to store with the cart item.
     *
     * @return array<string, mixed>
     */
    public function getBuyableAttributes(): array
    {
        return [
            'sku' => $this->getBuyableSku(),
            'weight' => $this->getBuyableWeight(),
            'taxable' => $this->isTaxable(),
            'tax_category' => $this->getTaxCategory(),
        ];
    }

    /**
     * Get the buyable item description.
     */
    public function getBuyableDescription(): ?string
    {
        return $this->description ?? $this->short_description ?? null;
    }

    /**
     * Get the SKU or product code.
     */
    public function getBuyableSku(): ?string
    {
        return $this->sku ?? null;
    }

    /**
     * Get the available stock quantity.
     */
    public function getBuyableStock(): ?int
    {
        if (! $this->tracksInventory()) {
            return null;
        }

        return $this->stock ?? $this->quantity ?? null;
    }

    /**
     * Get the weight in grams.
     */
    public function getBuyableWeight(): ?int
    {
        return $this->weight ?? null;
    }

    /**
     * Get the dimensions in millimeters.
     *
     * @return array{length: int, width: int, height: int}|null
     */
    public function getBuyableDimensions(): ?array
    {
        if (! isset($this->length, $this->width, $this->height)) {
            return null;
        }

        return [
            'length' => (int) $this->length,
            'width' => (int) $this->width,
            'height' => (int) $this->height,
        ];
    }

    /**
     * Get the minimum purchase quantity.
     */
    public function getMinimumQuantity(): int
    {
        return $this->min_quantity ?? 1;
    }

    /**
     * Get the maximum purchase quantity.
     */
    public function getMaximumQuantity(): ?int
    {
        return $this->max_quantity ?? null;
    }

    /**
     * Get the quantity increment.
     */
    public function getQuantityIncrement(): int
    {
        return $this->quantity_increment ?? 1;
    }

    /**
     * Check if this item is taxable.
     */
    public function isTaxable(): bool
    {
        return $this->taxable ?? $this->is_taxable ?? true;
    }

    /**
     * Get the tax category/class.
     */
    public function getTaxCategory(): ?string
    {
        return $this->tax_category ?? $this->tax_class ?? null;
    }

    /**
     * Check if this product tracks inventory.
     */
    protected function tracksInventory(): bool
    {
        return $this->tracks_inventory ?? $this->manage_stock ?? false;
    }
}

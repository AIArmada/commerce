<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Contracts;

/**
 * Interface for items that can have dynamic pricing.
 */
interface Priceable
{
    /**
     * Get the unique identifier for the priceable item.
     */
    public function getBuyableIdentifier(): string;

    /**
     * Get the base price in cents.
     */
    public function getBasePrice(): int;

    /**
     * Get the compare price (original/MSRP) in cents.
     */
    public function getComparePrice(): ?int;

    /**
     * Check if the item is on sale.
     */
    public function isOnSale(): bool;

    /**
     * Get the discount percentage if on sale.
     */
    public function getDiscountPercentage(): ?float;
}

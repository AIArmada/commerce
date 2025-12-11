<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

/**
 * Interface for items that can have dynamic pricing.
 */
interface Priceable
{
    /**
     * Get the base price in cents.
     */
    public function getBasePrice(): int;

    /**
     * Get the calculated price after applying rules.
     *
     * @param  array<string, mixed>  $context  Context for pricing (customer, quantity, etc.)
     */
    public function getCalculatedPrice(array $context = []): int;

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

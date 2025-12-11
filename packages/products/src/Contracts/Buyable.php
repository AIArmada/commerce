<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

/**
 * Interface for items that can be added to a cart.
 */
interface Buyable
{
    /**
     * Get the identifier for the buyable item.
     */
    public function getBuyableIdentifier(): string;

    /**
     * Get the description for the buyable item.
     */
    public function getBuyableDescription(): string;

    /**
     * Get the price for the buyable item in cents.
     */
    public function getBuyablePrice(): int;

    /**
     * Get the weight for shipping calculation.
     */
    public function getBuyableWeight(): ?float;

    /**
     * Check if the buyable item is available for purchase.
     */
    public function isBuyable(): bool;
}

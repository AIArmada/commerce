<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

/**
 * Interface for items that have inventory tracking.
 */
interface Inventoryable
{
    /**
     * Get the SKU for inventory tracking.
     */
    public function getInventorySku(): string;

    /**
     * Get the current stock quantity.
     */
    public function getStockQuantity(): int;

    /**
     * Check if the item is in stock.
     */
    public function isInStock(): bool;

    /**
     * Check if a specific quantity is available.
     */
    public function hasStock(int $quantity): bool;

    /**
     * Check if the item tracks inventory.
     */
    public function tracksInventory(): bool;
}

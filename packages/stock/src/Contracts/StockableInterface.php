<?php

declare(strict_types=1);

namespace AIArmada\Stock\Contracts;

use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Models\StockTransaction;

/**
 * Interface for models that can have stock tracked.
 *
 * This interface defines the contract for stock-aware products,
 * providing seamless integration with the cart package.
 */
interface StockableInterface
{
    /**
     * Get the current stock level.
     */
    public function getCurrentStock(): int;

    /**
     * Check if stock is available.
     */
    public function hasStock(int $quantity = 1): bool;

    /**
     * Check if stock is low.
     */
    public function isLowStock(?int $threshold = null): bool;

    /**
     * Add stock to the model.
     */
    public function addStock(
        int $quantity,
        string $reason = 'restock',
        ?string $note = null,
        ?string $userId = null
    ): StockTransaction;

    /**
     * Remove stock from the model.
     */
    public function removeStock(
        int $quantity,
        string $reason = 'adjustment',
        ?string $note = null,
        ?string $userId = null
    ): StockTransaction;

    /**
     * Get the available stock for purchase.
     *
     * This accounts for reserved stock (items in other carts).
     */
    public function getAvailableStock(): int;

    /**
     * Reserve stock for a cart.
     */
    public function reserveStock(int $quantity, string $cartId, int $ttlMinutes = 30): ?StockReservation;

    /**
     * Release reserved stock.
     */
    public function releaseReservedStock(string $cartId): bool;
}

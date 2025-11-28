<?php

declare(strict_types=1);

namespace AIArmada\Stock\Traits;

use AIArmada\Stock\Models\StockReservation;
use AIArmada\Stock\Models\StockTransaction;
use AIArmada\Stock\Services\StockReservationService;
use AIArmada\Stock\Services\StockService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStock
{
    /**
     * Get all stock transactions for the model.
     */
    public function stockTransactions(): MorphMany
    {
        return $this->morphMany(StockTransaction::class, 'stockable')
            ->orderBy('transaction_date', 'desc');
    }

    /**
     * Get all stock reservations for the model.
     */
    public function stockReservations(): MorphMany
    {
        return $this->morphMany(StockReservation::class, 'stockable');
    }

    /**
     * Get the current stock level.
     */
    public function getCurrentStock(): int
    {
        return $this->getStockService()->getCurrentStock($this);
    }

    /**
     * Get available stock (accounting for reservations).
     */
    public function getAvailableStock(): int
    {
        return $this->getReservationService()->getAvailableStock($this);
    }

    /**
     * Add stock to the model.
     */
    public function addStock(int $quantity, string $reason = 'restock', ?string $note = null, ?string $userId = null): StockTransaction
    {
        return $this->getStockService()->addStock(
            model: $this,
            quantity: $quantity,
            reason: $reason,
            note: $note,
            userId: $userId
        );
    }

    /**
     * Remove stock from the model.
     */
    public function removeStock(int $quantity, string $reason = 'adjustment', ?string $note = null, ?string $userId = null): StockTransaction
    {
        return $this->getStockService()->removeStock(
            model: $this,
            quantity: $quantity,
            reason: $reason,
            note: $note,
            userId: $userId
        );
    }

    /**
     * Get the stock service instance.
     */
    protected function getStockService(): StockService
    {
        return app(StockService::class);
    }

    /**
     * Get the stock reservation service instance.
     */
    protected function getReservationService(): StockReservationService
    {
        return app(StockReservationService::class);
    }

    /**
     * Reserve stock for a cart.
     */
    public function reserveStock(int $quantity, string $cartId, int $ttlMinutes = 30): ?StockReservation
    {
        return $this->getReservationService()->reserve($this, $quantity, $cartId, $ttlMinutes);
    }

    /**
     * Release reserved stock for a cart.
     */
    public function releaseReservedStock(string $cartId): bool
    {
        return $this->getReservationService()->release($this, $cartId);
    }

    /**
     * Get reservation for a specific cart.
     */
    public function getReservation(string $cartId): ?StockReservation
    {
        return $this->getReservationService()->getReservation($this, $cartId);
    }

    /**
     * Get total reserved quantity.
     */
    public function getReservedQuantity(): int
    {
        return $this->getReservationService()->getReservedQuantity($this);
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(?int $threshold = null): bool
    {
        return $this->getStockService()->isLowStock($this, $threshold);
    }

    /**
     * Check if stock is available.
     */
    public function hasStock(int $quantity = 1): bool
    {
        return $this->getStockService()->hasStock($this, $quantity);
    }

    /**
     * Check if available stock is sufficient (accounting for reservations).
     */
    public function hasAvailableStock(int $quantity = 1): bool
    {
        return $this->getReservationService()->hasAvailableStock($this, $quantity);
    }

    /**
     * Get stock history with optional limit.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, StockTransaction>
     */
    public function getStockHistory(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getStockService()->getStockHistory($this, $limit);
    }
}

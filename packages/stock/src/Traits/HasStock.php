<?php

declare(strict_types=1);

namespace AIArmada\Stock\Traits;

use AIArmada\Stock\Models\StockTransaction;
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
     * Get the current stock level.
     */
    public function getCurrentStock(): int
    {
        $inbound = $this->stockTransactions()
            ->where('type', 'in')
            ->sum('quantity');

        $outbound = $this->stockTransactions()
            ->where('type', 'out')
            ->sum('quantity');

        return (int) ($inbound - $outbound);
    }

    /**
     * Add stock to the model.
     */
    public function addStock(int $quantity, string $reason = 'restock', ?string $note = null, ?string $userId = null): StockTransaction
    {
        return $this->stockTransactions()->create([
            'quantity' => $quantity,
            'type' => 'in',
            'reason' => $reason,
            'note' => $note,
            'user_id' => $userId ?? auth()->id(),
            'transaction_date' => now(),
        ]);
    }

    /**
     * Remove stock from the model.
     */
    public function removeStock(int $quantity, string $reason = 'adjustment', ?string $note = null, ?string $userId = null): StockTransaction
    {
        return $this->stockTransactions()->create([
            'quantity' => $quantity,
            'type' => 'out',
            'reason' => $reason,
            'note' => $note,
            'user_id' => $userId ?? auth()->id(),
            'transaction_date' => now(),
        ]);
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(?int $threshold = null): bool
    {
        $threshold = $threshold ?? config('stock.low_stock_threshold', 10);

        return $this->getCurrentStock() < $threshold;
    }

    /**
     * Check if stock is available.
     */
    public function hasStock(int $quantity = 1): bool
    {
        return $this->getCurrentStock() >= $quantity;
    }

    /**
     * Get stock history with optional limit.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, StockTransaction>
     */
    public function getStockHistory(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $this->stockTransactions()
            ->with('user')
            ->latest('transaction_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}

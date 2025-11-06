<?php

declare(strict_types=1);

namespace AIArmada\Stock\Services;

use AIArmada\Stock\Models\StockTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class StockService
{
    /**
     * Add stock to a model.
     */
    public function addStock(
        Model $model,
        int $quantity,
        string $reason = 'restock',
        ?string $note = null,
        ?string $userId = null
    ): StockTransaction {
        return $this->createTransaction(
            model: $model,
            quantity: $quantity,
            type: 'in',
            reason: $reason,
            note: $note,
            userId: $userId
        );
    }

    /**
     * Remove stock from a model.
     */
    public function removeStock(
        Model $model,
        int $quantity,
        string $reason = 'adjustment',
        ?string $note = null,
        ?string $userId = null
    ): StockTransaction {
        return $this->createTransaction(
            model: $model,
            quantity: $quantity,
            type: 'out',
            reason: $reason,
            note: $note,
            userId: $userId
        );
    }

    /**
     * Adjust stock (automatic correction).
     */
    public function adjustStock(
        Model $model,
        int $currentStock,
        int $actualStock,
        ?string $note = null,
        ?string $userId = null
    ): ?StockTransaction {
        $difference = $actualStock - $currentStock;

        if ($difference === 0) {
            return null;
        }

        $type = $difference > 0 ? 'in' : 'out';
        $quantity = abs($difference);

        return $this->createTransaction(
            model: $model,
            quantity: $quantity,
            type: $type,
            reason: 'adjustment',
            note: $note ?? "Stock count correction: {$currentStock} → {$actualStock}",
            userId: $userId
        );
    }

    /**
     * Get current stock level for a model.
     */
    public function getCurrentStock(Model $model): int
    {
        $inbound = StockTransaction::query()
            ->where('stockable_type', $model->getMorphClass())
            ->where('stockable_id', $model->getKey())
            ->where('type', 'in')
            ->sum('quantity');

        $outbound = StockTransaction::query()
            ->where('stockable_type', $model->getMorphClass())
            ->where('stockable_id', $model->getKey())
            ->where('type', 'out')
            ->sum('quantity');

        return (int) ($inbound - $outbound);
    }

    /**
     * Get stock history for a model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, StockTransaction>
     */
    public function getStockHistory(Model $model, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return StockTransaction::query()
            ->where('stockable_type', $model->getMorphClass())
            ->where('stockable_id', $model->getKey())
            ->with('user')
            ->latest('transaction_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if model has sufficient stock.
     */
    public function hasStock(Model $model, int $quantity = 1): bool
    {
        return $this->getCurrentStock($model) >= $quantity;
    }

    /**
     * Check if stock is low for a model.
     */
    public function isLowStock(Model $model, ?int $threshold = null): bool
    {
        $threshold = $threshold ?? config('stock.low_stock_threshold', 10);

        return $this->getCurrentStock($model) < $threshold;
    }

    /**
     * Create a stock transaction.
     */
    private function createTransaction(
        Model $model,
        int $quantity,
        string $type,
        string $reason,
        ?string $note = null,
        ?string $userId = null
    ): StockTransaction {
        return DB::transaction(function () use (
            $model,
            $quantity,
            $type,
            $reason,
            $note,
            $userId
        ) {
            return StockTransaction::create([
                'stockable_type' => $model->getMorphClass(),
                'stockable_id' => $model->getKey(),
                'user_id' => $userId ?? auth()->id(),
                'quantity' => $quantity,
                'type' => $type,
                'reason' => $reason,
                'note' => $note,
                'transaction_date' => now(),
            ]);
        });
    }
}

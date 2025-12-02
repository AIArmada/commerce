<?php

declare(strict_types=1);

namespace AIArmada\Stock\Services;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Stock\Models\StockTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class StockService
{
    public function __construct(
        private OwnerResolverInterface $ownerResolver
    ) {}

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
     *
     * Uses a single atomic query to prevent race conditions.
     */
    public function getCurrentStock(Model $model): int
    {
        $result = $this->query()
            ->where('stockable_type', $model->getMorphClass())
            ->where('stockable_id', $model->getKey())
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END), 0) as stock
            ")
            ->value('stock');

        return (int) ($result ?? 0);
    }

    /**
     * Get stock history for a model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, StockTransaction>
     */
    public function getStockHistory(Model $model, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
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
     * Get the base query with owner scoping applied.
     *
     * @return Builder<StockTransaction>
     */
    private function query(): Builder
    {
        return StockTransaction::query()->forOwner(
            $this->resolveOwner(),
            $this->shouldIncludeGlobal()
        );
    }

    /**
     * Resolve the current owner.
     */
    private function resolveOwner(): ?Model
    {
        if (! config('stock.owner.enabled', false)) {
            return null;
        }

        return $this->ownerResolver->resolve();
    }

    /**
     * Determine if global records should be included.
     */
    private function shouldIncludeGlobal(): bool
    {
        return (bool) config('stock.owner.include_global', true);
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
            // Safely get auth ID - may be null in CLI/queue contexts
            $resolvedUserId = $userId;
            if ($resolvedUserId === null && function_exists('auth') && auth()->check()) {
                $resolvedUserId = (string) auth()->id();
            }

            $payload = [
                'stockable_type' => $model->getMorphClass(),
                'stockable_id' => $model->getKey(),
                'user_id' => $resolvedUserId,
                'quantity' => $quantity,
                'type' => $type,
                'reason' => $reason,
                'note' => $note,
                'transaction_date' => now(),
            ];

            // Auto-assign owner if enabled
            if (
                config('stock.owner.enabled', false)
                && config('stock.owner.auto_assign_on_create', true)
            ) {
                $owner = $this->resolveOwner();

                if ($owner) {
                    $payload['owner_type'] = $owner->getMorphClass();
                    $payload['owner_id'] = $owner->getKey();
                }
            }

            return StockTransaction::create($payload);
        });
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Queries;

use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class BatchLookupService
{
    /**
     * Find batch by batch number.
     */
    public function findByBatchNumber(string $batchNumber): ?InventoryBatch
    {
        return InventoryOwnerScope::applyToLocationQuery(
            InventoryBatch::query()->where('batch_number', $batchNumber)
        )->first();
    }

    /**
     * Get batches for a model.
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getBatchesForModel(Model $model): Collection
    {
        return InventoryOwnerScope::applyToLocationQuery(
            InventoryBatch::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->orderBy('expires_at')
                ->orderBy('received_at')
        )->get();
    }

    /**
     * Get allocatable batches for a model using FEFO.
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getAllocatableBatches(Model $model, ?string $locationId = null): Collection
    {
        return $this->allocatableQuery($model, $locationId)->fefo()->orderBy('id')->get();
    }

    /** @return Collection<int, InventoryBatch> */
    public function getFifoBatches(Model $model, ?string $locationId = null): Collection
    {
        return $this->allocatableQuery($model, $locationId)->fifo()->orderBy('id')->get();
    }

    public function getTotalAvailable(Model $model, ?string $locationId = null): int
    {
        return (int) $this->allocatableQuery($model, $locationId)
            ->sum(DB::raw('quantity_on_hand - quantity_reserved'));
    }

    /** @return Builder<InventoryBatch> */
    private function allocatableQuery(Model $model, ?string $locationId): Builder
    {
        $query = InventoryOwnerScope::forMutation(
            InventoryBatch::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->allocatable()
                ->whereHas('location', fn (Builder $location): Builder => InventoryOwnerScope::forMutation($location)->where('is_active', true))
        );

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return $query;
    }
}

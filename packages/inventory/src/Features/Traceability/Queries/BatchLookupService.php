<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Queries;

use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
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
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryBatch::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->allocatable()
                ->fefo()
        );

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return $query->get();
    }

    /**
     * Get total available quantity across all batches.
     */
    public function getTotalAvailable(Model $model, ?string $locationId = null): int
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryBatch::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->allocatable()
        );

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return (int) $query->sum(DB::raw('quantity_on_hand - quantity_reserved'));
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Persistence;

use AIArmada\Inventory\Contracts\BatchReservationInvalidator;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Traceability\Events\BatchCreated;
use AIArmada\Inventory\Features\Traceability\Events\BatchExpired;
use AIArmada\Inventory\Features\Traceability\Events\BatchRecalled;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Models\InventorySerial;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Manages batch/lot tracking for inventory items.
 *
 * Batch tracking enables FIFO picking, expiration management, and
 * traceability for regulated industries (food, pharma, etc.).
 *
 * @example Create a batch with expiration
 * ```php
 * $service = app(BatchService::class);
 * $batch = $service->createBatch(
 *     model: $product,
 *     batchNumber: 'LOT-2024-001',
 *     locationId: $warehouse->id,
 *     quantity: 100,
 *     expiresAt: CarbonImmutable::now()->addMonths(6),
 *     lotNumber: 'MFG-2024-A1',
 *     unitCostMinor: 1500,  // $15.00
 * );
 * ```
 * @example Pick from earliest expiring batch (FEFO)
 * ```php
 * $batch = $service->getEarliestExpiringBatch($product);
 * $service->decrementBatch($batch, 5);
 * ```
 * @example Handle product recall
 * ```php
 * $batches = $service->recallBatch('LOT-2024-001', 'FDA Safety Alert #12345');
 * // All affected batches are now quarantined
 * ```
 */
final readonly class BatchService
{
    public function __construct(
        private LocationVisibility $locations,
        private BatchReservationInvalidator $reservations,
    ) {}

    /**
     * Create a new batch.
     *
     * @param  Model  $model  The inventoryable model (e.g., Product)
     * @param  string  $batchNumber  Unique batch/lot identifier
     * @param  string  $locationId  Location UUID where batch is stored
     * @param  int  $quantity  Initial quantity received
     * @param  CarbonImmutable|null  $expiresAt  Expiration date (null = never expires)
     * @param  CarbonImmutable|null  $manufacturedAt  Manufacturing/production date
     * @param  string|null  $lotNumber  Additional lot number (if different from batch)
     * @param  int|null  $unitCostMinor  Unit cost in minor units (cents)
     * @param  string|null  $supplierId  Supplier/vendor identifier
     * @param  string|null  $purchaseOrderNumber  PO reference
     * @return InventoryBatch The created batch record
     *
     * @throws InvalidArgumentException If quantity is not positive or location is invalid
     */
    public function createBatch(
        Model $model,
        string $batchNumber,
        string $locationId,
        int $quantity,
        ?CarbonImmutable $expiresAt = null,
        ?CarbonImmutable $manufacturedAt = null,
        ?string $lotNumber = null,
        ?int $unitCostMinor = null,
        ?string $supplierId = null,
        ?string $purchaseOrderNumber = null
    ): InventoryBatch {
        $batchNumber = mb_trim($batchNumber);

        if (! $model->exists || $model->getKey() === null || $batchNumber === '') {
            throw new InvalidArgumentException('A persisted item and nonempty batch number are required');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if ($manufacturedAt !== null && $expiresAt !== null && $expiresAt->lt($manufacturedAt)) {
            throw new InvalidArgumentException('Batch expiry cannot precede manufacture');
        }

        if ($unitCostMinor !== null && $unitCostMinor < 0) {
            throw new InvalidArgumentException('Unit cost cannot be negative');
        }

        return DB::transaction(function () use (
            $model,
            $batchNumber,
            $locationId,
            $quantity,
            $expiresAt,
            $manufacturedAt,
            $lotNumber,
            $unitCostMinor,
            $supplierId,
            $purchaseOrderNumber
        ): InventoryBatch {
            $this->assertOwnedLocation($locationId);

            $batch = InventoryBatch::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'batch_number' => $batchNumber,
                'lot_number' => $lotNumber,
                'location_id' => $locationId,
                'quantity_received' => $quantity,
                'quantity_on_hand' => $quantity,
                'quantity_reserved' => 0,
                'manufactured_at' => $manufacturedAt,
                'received_at' => CarbonImmutable::now(),
                'expires_at' => $expiresAt,
                'status' => BatchStatus::Active->value,
                'unit_cost_minor' => $unitCostMinor,
                'supplier_id' => $supplierId,
                'purchase_order_number' => $purchaseOrderNumber,
            ]);

            Event::dispatch(new BatchCreated($batch, $model));

            return $batch;
        });
    }

    /**
     * Quarantine a batch.
     */
    public function quarantine(InventoryBatch $batch, string $reason): InventoryBatch
    {
        return DB::transaction(function () use ($batch, $reason): InventoryBatch {
            $batch = $this->lockBatch($batch);
            if ($batch->status !== BatchStatus::Active->value || $batch->is_expired) {
                throw new InvalidArgumentException('Only active, unexpired batches can be quarantined');
            }

            $batch->update([
                'status' => BatchStatus::Quarantined->value,
                'quarantine_reason' => $reason,
                'quarantined_at' => CarbonImmutable::now(),
            ]);

            $this->releaseBatchAllocations($batch);

            return $batch->refresh();
        }, 3);
    }

    public function releaseFromQuarantine(InventoryBatch $batch): InventoryBatch
    {
        return DB::transaction(function () use ($batch): InventoryBatch {
            $batch = $this->lockBatch($batch);

            if ($batch->status !== BatchStatus::Quarantined->value || $batch->is_expired) {
                throw new InvalidArgumentException('Only unexpired quarantined batches can be released');
            }

            $batch->update([
                'status' => $batch->quantity_on_hand > 0 ? BatchStatus::Active->value : BatchStatus::Depleted->value,
                'quarantine_reason' => null,
                'quarantined_at' => null,
            ]);

            return $batch;
        }, 3);
    }

    /**
     * Recall multiple batches.
     *
     * Quarantines all specified batches and releases any associated
     * allocations. Use this for product recalls or quality issues.
     *
     * @param  Collection<int, InventoryBatch>|array<InventoryBatch>  $batches  Batches to recall
     * @param  string  $reason  Recall reason (e.g., FDA notice number)
     * @return Collection<int, InventoryBatch> The recalled batches
     */
    public function recallBatches(Collection | array $batches, string $reason): Collection
    {
        if (is_array($batches)) {
            $batches = new Collection($batches);
        }

        return DB::transaction(function () use ($batches, $reason): Collection {
            $batches = $this->lockBatches($batches);

            foreach ($batches as $batch) {
                $batch->update([
                    'status' => BatchStatus::Recalled->value,
                    'recall_reason' => $reason,
                    'recalled_at' => CarbonImmutable::now(),
                ]);

                $this->releaseBatchAllocations($batch);
            }

            // Get representative inventoryable for event
            $firstBatch = $batches->first();

            if ($firstBatch?->inventoryable !== null) {
                Event::dispatch(new BatchRecalled($batches, $reason, $firstBatch->inventoryable));
            }

            return $batches;
        }, 3);
    }

    /**
     * Transfer batch to a new location.
     */
    public function transferBatch(InventoryBatch $batch, string $newLocationId): InventoryBatch
    {
        return DB::transaction(function () use ($batch, $newLocationId): InventoryBatch {
            $batch = $this->lockBatch($batch);
            $this->assertOwnedLocation($newLocationId);

            if ($batch->quantity_reserved > 0 || $batch->allocations()->withoutGlobalScopes()->exists()) {
                throw new InvalidArgumentException('Cannot transfer batch with reserved quantity');
            }

            if ($batch->location_id !== $newLocationId && InventorySerial::query()
                ->withoutGlobalScopes()
                ->where('batch_id', $batch->getKey())
                ->whereNotNull('location_id')
                ->where('location_id', '!=', $newLocationId)
                ->exists()) {
                throw new InvalidArgumentException('Cannot transfer batch independently of its located serials');
            }

            $batch->update([
                'location_id' => $newLocationId,
            ]);

            return $batch->refresh();
        });
    }

    /**
     * Split a batch into two.
     *
     * Creates a new batch with the specified quantity, reducing the
     * original batch. Useful for partial shipments or re-packaging.
     *
     * @param  InventoryBatch  $batch  The batch to split
     * @param  int  $quantityToSplit  Quantity for the new batch
     * @param  string  $newBatchNumber  Batch number for the new batch
     * @return InventoryBatch The newly created batch
     *
     * @throws InvalidArgumentException If quantity is invalid or batch has reservations
     */
    public function splitBatch(InventoryBatch $batch, int $quantityToSplit, string $newBatchNumber): InventoryBatch
    {
        return DB::transaction(function () use ($batch, $quantityToSplit, $newBatchNumber): InventoryBatch {
            $batch = $this->lockBatch($batch);

            $newBatchNumber = mb_trim($newBatchNumber);

            if ($newBatchNumber === '' || $batch->status !== BatchStatus::Active->value || $batch->is_expired) {
                throw new InvalidArgumentException('Only active, unexpired batches can be split with a nonempty batch number');
            }

            if ($quantityToSplit <= 0 || $quantityToSplit >= $batch->available) {
                throw new InvalidArgumentException('Invalid quantity to split');
            }

            // Create new batch
            $newBatch = InventoryBatch::create([
                'inventoryable_type' => $batch->inventoryable_type,
                'inventoryable_id' => $batch->inventoryable_id,
                'batch_number' => $newBatchNumber,
                'lot_number' => $batch->lot_number,
                'supplier_batch_number' => $batch->supplier_batch_number,
                'location_id' => $batch->location_id,
                'quantity_received' => $quantityToSplit,
                'quantity_on_hand' => $quantityToSplit,
                'quantity_reserved' => 0,
                'manufactured_at' => $batch->manufactured_at,
                'received_at' => CarbonImmutable::now(),
                'expires_at' => $batch->expires_at,
                'status' => $batch->status,
                'unit_cost_minor' => $batch->unit_cost_minor,
                'currency' => $batch->currency,
                'supplier_id' => $batch->supplier_id,
                'metadata' => [
                    'split_from' => $batch->batch_number,
                    'split_at' => CarbonImmutable::now()->toIso8601String(),
                ],
            ]);

            // Reduce original batch
            $batch->decrement('quantity_on_hand', $quantityToSplit);

            return $newBatch;
        });
    }

    /**
     * Merge batches into one.
     *
     * Combines multiple batches of the same item into a single batch.
     * Uses the earliest expiration date. Cannot merge batches with
     * reserved quantities.
     *
     * @param  Collection<int, InventoryBatch>  $batches  Batches to merge (min 2)
     * @param  string  $newBatchNumber  Batch number for the merged batch
     * @return InventoryBatch The newly created merged batch
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function mergeBatches(Collection $batches, string $newBatchNumber): InventoryBatch
    {
        return DB::transaction(function () use ($batches, $newBatchNumber): InventoryBatch {
            $batches = $this->lockBatches($batches);
            $newBatchNumber = mb_trim($newBatchNumber);

            if ($newBatchNumber === '') {
                throw new InvalidArgumentException('Merged batch number must not be empty');
            }

            if ($batches->count() < 2) {
                throw new InvalidArgumentException('Need at least 2 batches to merge');
            }

            if ($batches->pluck('inventoryable_type')->unique()->count() > 1
                || $batches->pluck('inventoryable_id')->unique()->count() > 1
                || $batches->pluck('location_id')->unique()->count() > 1) {
                throw new InvalidArgumentException('All batches must be for the same item and location');
            }

            foreach ($batches as $batch) {
                if ($batch->quantity_reserved > 0 || $batch->hasTraceabilityReferences()) {
                    throw new InvalidArgumentException('Cannot merge batches with reservations or existing traceability references');
                }

                if ($batch->status !== BatchStatus::Active->value || $batch->is_expired) {
                    throw new InvalidArgumentException('Only active, unexpired batches can be merged');
                }
            }

            $firstBatch = $batches->firstOrFail();
            $totalQuantity = $batches->sum('quantity_on_hand');
            $earliestExpiry = $batches->whereNotNull('expires_at')->min('expires_at');

            // Create merged batch
            $mergedBatch = InventoryBatch::create([
                'inventoryable_type' => $firstBatch->inventoryable_type,
                'inventoryable_id' => $firstBatch->inventoryable_id,
                'batch_number' => $newBatchNumber,
                'location_id' => $firstBatch->location_id,
                'quantity_received' => $totalQuantity,
                'quantity_on_hand' => $totalQuantity,
                'quantity_reserved' => 0,
                'received_at' => CarbonImmutable::now(),
                'expires_at' => $earliestExpiry,
                'status' => BatchStatus::Active->value,
                'metadata' => [
                    'merged_from' => $batches->pluck('batch_number')->toArray(),
                    'merged_at' => CarbonImmutable::now()->toIso8601String(),
                ],
            ]);

            // Only unused source identities may be removed. A veto must roll back the merge.
            foreach ($batches as $batch) {
                if (! $batch->delete()) {
                    throw new InvalidArgumentException('Cannot remove a referenced source batch');
                }
            }

            return $mergedBatch;
        });
    }

    /**
     * Check and update expired batches.
     */
    public function processExpiredBatches(): int
    {
        return DB::transaction(function (): int {
            $expiredBatches = $this->ownedQuery()
                ->where('status', BatchStatus::Active->value)
                ->expired()
                ->orderBy('id')
                ->lockForUpdate()
                ->with('inventoryable')
                ->get();

            foreach ($expiredBatches as $batch) {
                $batch->update(['status' => BatchStatus::Expired->value]);
                $this->releaseBatchAllocations($batch);

                if ($batch->inventoryable !== null) {
                    Event::dispatch(new BatchExpired($batch, $batch->inventoryable));
                }
            }

            return $expiredBatches->count();
        }, 3);
    }

    private function releaseBatchAllocations(InventoryBatch $batch): void
    {
        $this->reservations->releaseForBatch($batch->id);
    }

    /** @return Builder<InventoryBatch> */
    private function ownedQuery(): Builder
    {
        return InventoryOwnerScope::forMutation(InventoryBatch::query());
    }

    private function lockBatch(InventoryBatch $batch): InventoryBatch
    {
        return $this->ownedQuery()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  Collection<int, InventoryBatch>  $batches
     * @return Collection<int, InventoryBatch>
     */
    private function lockBatches(Collection $batches): Collection
    {
        $ids = $batches->modelKeys();
        $locked = $this->ownedQuery()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

        if ($locked->count() !== count($ids)) {
            throw new InvalidArgumentException('Batches must be distinct and accessible for current owner');
        }

        return $locked;
    }

    private function assertOwnedLocation(string $locationId): void
    {
        if (! $this->locations->lockWritable($locationId)) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }
    }
}

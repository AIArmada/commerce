<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Persistence;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BatchAllocationService
{
    public function __construct(
        private BatchService $batchService
    ) {}

    /**
     * Allocate quantity using FEFO (First Expired, First Out) strategy.
     *
     * @return array<array{batch: InventoryBatch, quantity: int}>
     */
    public function allocateFefo(Model $model, int $quantity, ?string $locationId = null): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        $batches = $this->batchService->getAllocatableBatches($model, $locationId);

        return $this->allocateFromBatches($batches, $quantity);
    }

    /**
     * Allocate quantity using FIFO (First In, First Out) strategy.
     *
     * @return array<array{batch: InventoryBatch, quantity: int}>
     */
    public function allocateFifo(Model $model, int $quantity, ?string $locationId = null): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if (InventoryOwnerScope::isEnabled() && $locationId !== null) {
            $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($locationId)
                ->exists();

            if (! $isAllowed) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryBatch::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->allocatable()
                ->fifo()
        );

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return $this->allocateFromBatches($query->get(), $quantity);
    }

    /**
     * Reserve quantity across batches.
     *
     * @param  array<array{batch: InventoryBatch, quantity: int}>  $allocations
     */
    public function reserveBatches(array $allocations): void
    {
        DB::transaction(function () use ($allocations): void {
            foreach ($this->lockAllocations($allocations) as $allocation) {
                $batch = $allocation['batch'];
                $quantity = $allocation['quantity'];

                if (! $batch->canAllocate() || $quantity > $batch->available) {
                    throw new InvalidArgumentException('Insufficient allocatable batch inventory');
                }

                $batch->incrementReserved($quantity);
            }
        }, 3);
    }

    /**
     * Release reserved quantity from batches.
     *
     * @param  array<array{batch: InventoryBatch, quantity: int}>  $allocations
     */
    public function releaseBatches(array $allocations): void
    {
        DB::transaction(function () use ($allocations): void {
            foreach ($this->lockAllocations($allocations) as $allocation) {
                $batch = $allocation['batch'];
                $quantity = $allocation['quantity'];

                if ($quantity > $batch->quantity_reserved) {
                    throw new InvalidArgumentException('Cannot release more than the reserved batch quantity');
                }

                $batch->decrementReserved($quantity);
            }
        }, 3);
    }

    /**
     * Commit allocations (deduct from on-hand after payment).
     *
     * @param  array<array{batch: InventoryBatch, quantity: int}>  $allocations
     */
    public function commitBatches(array $allocations): void
    {
        DB::transaction(function () use ($allocations): void {
            foreach ($this->lockAllocations($allocations) as $allocation) {
                $batch = $allocation['batch'];
                $quantity = $allocation['quantity'];

                if ($quantity > $batch->quantity_reserved || $quantity > $batch->quantity_on_hand) {
                    throw new InvalidArgumentException('Cannot commit more than the reserved or on-hand batch quantity');
                }

                $batch->decrementReserved($quantity);
                $batch->decrementOnHand($quantity);
            }
        }, 3);
    }

    /**
     * Check availability across batches.
     */
    public function checkAvailability(Model $model, int $quantity, ?string $locationId = null): bool
    {
        return $this->batchService->getTotalAvailable($model, $locationId) >= $quantity;
    }

    /**
     * Get allocation plan for a quantity.
     *
     * @return array{
     *     available: bool,
     *     total_available: int,
     *     requested: int,
     *     allocations: array<array{batch_id: string, batch_number: string, quantity: int, expires_at: string|null}>
     * }
     */
    public function getFefoAllocationPlan(Model $model, int $quantity, ?string $locationId = null): array
    {
        $batches = $this->batchService->getAllocatableBatches($model, $locationId);
        $totalAvailable = $batches->sum(fn (InventoryBatch $b): int => $b->available);

        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->available;

            if ($available <= 0) {
                continue;
            }

            $toAllocate = min($remaining, $available);
            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $toAllocate,
                'expires_at' => $batch->expires_at?->toDateString(),
            ];

            $remaining -= $toAllocate;
        }

        return [
            'available' => $remaining === 0,
            'total_available' => $totalAvailable,
            'requested' => $quantity,
            'allocations' => $allocations,
        ];
    }

    /**
     * @param  array<array{batch: InventoryBatch, quantity: int}>  $allocations
     * @return array<array{batch: InventoryBatch, quantity: int}>
     */
    private function lockAllocations(array $allocations): array
    {
        $quantities = [];

        foreach ($allocations as $allocation) {
            $quantity = $allocation['quantity'];

            if ($quantity <= 0) {
                throw new InvalidArgumentException('Quantity must be positive');
            }

            $id = $allocation['batch']->id;
            $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
        }

        if ($quantities === []) {
            return [];
        }

        $query = InventoryBatch::query();

        if (InventoryOwnerScope::isEnabled()) {
            $owner = InventoryOwnerScope::resolveOwner();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $query->forOwner($owner, false);
        }

        $batches = $query->whereKey(array_keys($quantities))->orderBy('id')->lockForUpdate()->get();

        if ($batches->count() !== count($quantities)) {
            throw new InvalidArgumentException('One or more batches are not accessible for current owner');
        }

        $locked = [];

        foreach ($batches as $batch) {
            $locked[] = ['batch' => $batch, 'quantity' => $quantities[$batch->id]];
        }

        return $locked;
    }

    /**
     * Allocate from a collection of batches.
     *
     * @param  Collection<int, InventoryBatch>  $batches
     * @return array<array{batch: InventoryBatch, quantity: int}>
     */
    private function allocateFromBatches(Collection $batches, int $quantity): array
    {
        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->available;

            if ($available <= 0) {
                continue;
            }

            $toAllocate = min($remaining, $available);
            $allocations[] = [
                'batch' => $batch,
                'quantity' => $toAllocate,
            ];

            $remaining -= $toAllocate;
        }

        if ($remaining > 0) {
            throw new InvalidArgumentException(
                sprintf('Insufficient batch inventory. Requested: %d, Available: %d', $quantity, $quantity - $remaining)
            );
        }

        return $allocations;
    }
}

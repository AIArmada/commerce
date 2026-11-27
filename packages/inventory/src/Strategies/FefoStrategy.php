<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Strategies;

use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * FEFO (First Expired, First Out) allocation strategy.
 * Prioritizes batches that expire soonest to minimize waste.
 */
/** @implements AllocationStrategyInterface<InventoryBatch> */
final class FefoStrategy implements AllocationStrategyInterface
{
    public function name(): string
    {
        return 'fefo';
    }

    public function label(): string
    {
        return 'First Expired, First Out';
    }

    public function description(): string
    {
        return 'Allocates inventory from batches with the earliest expiry dates first, minimizing waste for perishable goods.';
    }

    /**
     * @return array<int, array{batch_id: string, location_id: string|null, quantity: int, expires_at: CarbonImmutable|null}>
     */
    public function allocate(Model $model, int $quantity, ?AllocationContext $context = null): array
    {
        $context ??= new AllocationContext;

        if ($quantity <= 0) {
            return [];
        }

        $batches = $this->eligibleBatches($model, $context)->fefo()->orderBy('id')->get();
        $allocations = $this->buildAllocations($batches, $quantity);

        if (! $context->allowPartialAllocation && array_sum(array_column($allocations, 'quantity')) < $quantity) {
            return [];
        }

        return $allocations;
    }

    public function canFulfill(Model $model, int $quantity, ?AllocationContext $context = null): bool
    {
        $context ??= new AllocationContext;

        if ($quantity <= 0) {
            return false;
        }

        $available = (int) $this->eligibleBatches($model, $context)
            ->selectRaw('SUM(quantity_on_hand - quantity_reserved) as total')
            ->value('total');

        return $available >= $quantity;
    }

    /**
     * @return Collection<int, InventoryBatch>
     */
    public function getRecommendedOrder(Model $model, ?AllocationContext $context = null): Collection
    {
        return $this->eligibleBatches($model, $context ?? new AllocationContext)
            ->fefo()
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<InventoryBatch>
     */
    private function eligibleBatches(Model $model, AllocationContext $context): Builder
    {
        if (InventoryOwnerScope::isEnabled() && $context->locationId !== null) {
            $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($context->locationId)
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
        );

        if ($context->locationId !== null) {
            $query->atLocation($context->locationId);
        }

        if ($context->excludeLocationIds !== null) {
            $query->whereNotIn('location_id', $context->excludeLocationIds);
        }

        if ($context->excludeExpiringSoon) {
            $minimumExpiryDate = CarbonImmutable::now()->addDays($context->minDaysToExpiry);

            $query->where(function (Builder $expiryQuery) use ($minimumExpiryDate): void {
                $expiryQuery->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $minimumExpiryDate);
            });
        }

        return $query;
    }

    /**
     * @param  Collection<int, InventoryBatch>  $batches
     * @return array<int, array{batch_id: string, location_id: string|null, quantity: int, expires_at: CarbonImmutable|null}>
     */
    private function buildAllocations(Collection $batches, int $quantity): array
    {
        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->available;
            $allocate = min($available, $remaining);

            if ($allocate > 0) {
                $allocations[] = [
                    'batch_id' => $batch->id,
                    'location_id' => $batch->location_id,
                    'quantity' => $allocate,
                    'expires_at' => $batch->expires_at,
                ];

                $remaining -= $allocate;
            }
        }

        return $allocations;
    }
}

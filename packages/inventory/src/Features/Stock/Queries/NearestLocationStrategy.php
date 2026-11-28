<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Data\AllocationContext;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Nearest Location allocation strategy.
 * Allocates from locations closest to a specified origin point.
 */
final class NearestLocationStrategy
{
    public function name(): string
    {
        return 'nearest_location';
    }

    public function label(): string
    {
        return 'Nearest Location';
    }

    public function description(): string
    {
        return 'Allocates inventory from locations closest to the specified origin, optimizing for picking efficiency.';
    }

    /**
     * @return array<int, array{location_id: string, quantity: int, distance: float}>
     */
    public function allocate(Model $model, int $quantity, ?AllocationContext $context = null): array
    {
        $context = $context ?? new AllocationContext;

        if ($quantity <= 0) {
            return [];
        }

        $levels = $this->getAvailableLevels($model, $context);
        $sortedLevels = $this->sortByDistance($levels, $context);
        $allocations = $this->buildAllocations($sortedLevels, $quantity, $context);

        if (! $context->allowPartialAllocation && array_sum(array_column($allocations, 'quantity')) < $quantity) {
            return [];
        }

        return $allocations;
    }

    public function canFulfill(Model $model, int $quantity, ?AllocationContext $context = null): bool
    {
        $context = $context ?? new AllocationContext;

        if ($quantity <= 0) {
            return false;
        }

        if ($context->maxLocations !== null) {
            return array_sum(array_column($this->allocate($model, $quantity, $context), 'quantity')) >= $quantity;
        }

        $available = (int) $this->eligibleLevels($model, $context)
            ->selectRaw('SUM(quantity_on_hand - quantity_reserved) as total')
            ->value('total');

        return $available >= $quantity;
    }

    /**
     * @return Collection<int, InventoryLocation>
     */
    public function getRecommendedOrder(Model $model, ?AllocationContext $context = null): Collection
    {
        $context = $context ?? new AllocationContext;

        $levels = $this->getAvailableLevels($model, $context);
        $sortedLevels = $this->sortByDistance($levels, $context);

        $locations = [];

        foreach ($sortedLevels as $level) {
            $location = $level->location;

            if ($location !== null && ! isset($locations[$location->id])) {
                $locations[$location->id] = $location;
            }
        }

        return new Collection(array_values($locations));
    }

    /**
     * Get locations sorted by distance from origin.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getLocationsByDistance(float $originX, float $originY, ?float $originZ = null): Collection
    {
        $locationsQuery = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->where('is_active', true)
            ->orderBy('id');

        $locations = $locationsQuery->get();

        return $locations->sortBy(function ($location) use ($originX, $originY, $originZ) {
            return $this->calculateDistance(
                $originX,
                $originY,
                $originZ,
                $location->coordinate_x !== null ? (float) $location->coordinate_x : null,
                $location->coordinate_y !== null ? (float) $location->coordinate_y : null,
                $location->coordinate_z !== null ? (float) $location->coordinate_z : null
            );
        })->values();
    }

    /**
     * @return Collection<int, InventoryLevel>
     */
    private function getAvailableLevels(Model $model, AllocationContext $context): Collection
    {
        return $this->eligibleLevels($model, $context)
            ->with('location')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<InventoryLevel>
     */
    private function eligibleLevels(Model $model, AllocationContext $context): Builder
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereRaw('(quantity_on_hand - quantity_reserved) > 0')
                ->whereHas('location', fn (Builder $locationQuery): Builder => InventoryOwnerScope::applyToLocationQuery($locationQuery)->where('is_active', true))
        );

        if ($context->locationId !== null) {
            $query->where('location_id', $context->locationId);
        }

        if ($context->excludeLocationIds !== null) {
            $query->whereNotIn('location_id', $context->excludeLocationIds);
        }

        return $query;
    }

    /**
     * @param  Collection<int, InventoryLevel>  $levels
     * @return Collection<int, InventoryLevel>
     */
    private function sortByDistance(Collection $levels, AllocationContext $context): Collection
    {
        $preferred = array_flip(array_values(array_unique($context->preferLocationIds ?? [])));
        $fallbackRank = count($preferred);

        return $levels->sortBy(function (InventoryLevel $level) use ($context, $preferred, $fallbackRank): array {
            $location = $level->location;
            $rank = $preferred[$level->location_id] ?? $fallbackRank;
            $distance = $context->hasOriginCoordinates()
                ? $this->calculateDistance(
                    $context->originX,
                    $context->originY,
                    $context->originZ,
                    $location?->coordinate_x !== null ? (float) $location->coordinate_x : null,
                    $location?->coordinate_y !== null ? (float) $location->coordinate_y : null,
                    $location?->coordinate_z !== null ? (float) $location->coordinate_z : null,
                )
                : ($location?->pick_sequence ?? 0);

            return [$rank, $distance, $level->id];
        })->values();
    }

    private function calculateDistance(
        ?float $x1,
        ?float $y1,
        ?float $z1,
        ?float $x2,
        ?float $y2,
        ?float $z2
    ): float {
        if ($x1 === null || $y1 === null || $x2 === null || $y2 === null) {
            return PHP_FLOAT_MAX;
        }

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $dz = ($z2 ?? 0) - ($z1 ?? 0);

        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }

    /**
     * @param  Collection<int, InventoryLevel>  $levels
     * @return array<int, array{location_id: string, quantity: int, distance: float}>
     */
    private function buildAllocations(Collection $levels, int $quantity, AllocationContext $context): array
    {
        $allocations = [];
        $remaining = $quantity;
        $locationCount = 0;

        foreach ($levels as $level) {
            if ($remaining <= 0) {
                break;
            }

            if ($context->maxLocations !== null && $locationCount >= $context->maxLocations) {
                break;
            }

            $available = $level->available;
            $allocate = min($available, $remaining);

            if ($allocate > 0) {
                $location = $level->location;
                $distance = 0.0;

                if ($context->hasOriginCoordinates() && $location) {
                    $distance = $this->calculateDistance(
                        $context->originX,
                        $context->originY,
                        $context->originZ,
                        $location->coordinate_x !== null ? (float) $location->coordinate_x : null,
                        $location->coordinate_y !== null ? (float) $location->coordinate_y : null,
                        $location->coordinate_z !== null ? (float) $location->coordinate_z : null
                    );
                }

                $allocations[] = [
                    'location_id' => $level->location_id,
                    'quantity' => $allocate,
                    'distance' => $distance,
                ];

                $remaining -= $allocate;
                $locationCount++;
            }
        }

        return $allocations;
    }
}

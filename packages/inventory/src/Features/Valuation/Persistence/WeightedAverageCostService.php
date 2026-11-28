<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Persistence;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Traceability\Api\BatchIdentity;
use AIArmada\Inventory\Features\Valuation\Models\InventoryCostLayer;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class WeightedAverageCostService
{
    /**
     * Record a purchase and update weighted average.
     *
     * @return array{new_average_cost: int, layer: InventoryCostLayer}
     */
    public function recordPurchase(
        Model $model,
        int $quantity,
        int $unitCostMinor,
        ?string $locationId = null,
        ?string $batchId = null,
        ?string $reference = null,
        ?CarbonImmutable $layerDate = null
    ): array {
        if ($quantity <= 0 || $unitCostMinor < 0) {
            throw new InvalidArgumentException('Purchase quantity must be positive and unit cost non-negative.');
        }

        return DB::transaction(function () use ($model, $quantity, $unitCostMinor, $locationId, $batchId, $reference, $layerDate): array {
            if (InventoryOwnerScope::isEnabled()) {
                if ($locationId === null && InventoryOwnerScope::resolveOwner() !== null) {
                    throw new InvalidArgumentException('Location is required when owner scoping is enabled');
                }

                if ($locationId !== null) {
                    $isAllowed = app(LocationVisibility::class)->isWritable($locationId);

                    if (! $isAllowed) {
                        throw new InvalidArgumentException('Invalid location for current owner');
                    }
                }

                if ($batchId !== null) {
                    $isAllowed = app(BatchIdentity::class)->matchesWritableBatch($batchId, $model->getMorphClass(), (string) $model->getKey(), $locationId);

                    if (! $isAllowed) {
                        throw new InvalidArgumentException('Invalid batch for current owner');
                    }
                }
            }

            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $currentValuation = $this->valuation($model, $locationId, true);

            $totalQuantity = $currentValuation['quantity'] + $quantity;
            $totalValue = $currentValuation['value'] + ($quantity * $unitCostMinor);
            $newAverageCost = $totalQuantity > 0 ? (int) round($totalValue / $totalQuantity) : $unitCostMinor;

            $layer = InventoryCostLayer::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'location_id' => $locationId,
                'batch_id' => $batchId,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost_minor' => $newAverageCost,
                'total_cost_minor' => $quantity * $newAverageCost,
                'currency' => config('inventory.defaults.currency', 'MYR'),
                'reference' => $reference,
                'costing_method' => CostingMethod::WeightedAverage,
                'layer_date' => $layerDate ?? CarbonImmutable::now(),
            ]);

            $this->updateExistingLayers($model, $newAverageCost, $locationId);

            return [
                'new_average_cost' => $newAverageCost,
                'layer' => $layer,
            ];
        });
    }

    /**
     * Consume quantity using weighted average cost.
     *
     * @return array{consumed: int, cost: int, unit_cost: int}
     */
    public function consume(
        Model $model,
        int $quantity,
        ?string $locationId = null
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Consumption quantity must be positive.');
        }

        return DB::transaction(function () use ($model, $quantity, $locationId): array {
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $valuation = $this->valuation($model, $locationId, true);

            if ($valuation['quantity'] < $quantity) {
                $quantity = $valuation['quantity'];
            }

            $unitCost = $valuation['average_cost'];

            $query = InventoryOwnerScope::forMutation(
                InventoryCostLayer::query()
                    ->forModel($model)
                    ->withRemainingQuantity()
                    ->usingMethod(CostingMethod::WeightedAverage)
                    ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
                    ->orderBy('layer_date', 'asc')
            );

            if (InventoryOwnerScope::isEnabled()) {
                $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

                if (! $includeNullLocation) {
                    $query->whereNotNull('location_id');
                }
            }

            if ($locationId !== null) {
                if (InventoryOwnerScope::isEnabled()) {
                    $isAllowed = app(LocationVisibility::class)->isWritable($locationId);

                    if (! $isAllowed) {
                        throw new InvalidArgumentException('Invalid location for current owner');
                    }
                }

                $query->where('location_id', $locationId);
            }

            $layers = $query->orderBy('id')->lockForUpdate()->get();
            $remaining = $quantity;

            foreach ($layers as $layer) {
                if ($remaining <= 0) {
                    break;
                }

                $consumed = $layer->consume($remaining);
                $remaining -= $consumed;
            }

            return [
                'consumed' => $quantity - $remaining,
                'cost' => ($quantity - $remaining) * $unitCost,
                'unit_cost' => $unitCost,
            ];
        });
    }

    /**
     * Calculate weighted average valuation for a model.
     *
     * @return array{quantity: int, value: int, average_cost: int}
     */
    public function calculateValuation(Model $model, ?string $locationId = null): array
    {
        return $this->valuation($model, $locationId, false);
    }

    /** @return array{quantity: int, value: int, average_cost: int} */
    private function valuation(Model $model, ?string $locationId, bool $forMutation): array
    {
        $scope = $forMutation ? InventoryOwnerScope::forMutation(...) : InventoryOwnerScope::applyToLocationQuery(...);
        $query = $scope(
            InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::WeightedAverage)
                ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
        );

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            if (! $includeNullLocation) {
                $query->whereNotNull('location_id');
            }
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = $forMutation ? app(LocationVisibility::class)->isWritable($locationId) : app(LocationVisibility::class)->isVisible($locationId);

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        $totals = $query->selectRaw('COALESCE(SUM(remaining_quantity), 0) as total_quantity, COALESCE(SUM(remaining_quantity * unit_cost_minor), 0) as total_value')->firstOrFail();
        $totalQuantity = (int) $totals->getAttribute('total_quantity');
        $totalValue = (int) $totals->getAttribute('total_value');

        return [
            'quantity' => $totalQuantity,
            'value' => $totalValue,
            'average_cost' => $totalQuantity > 0 ? (int) round($totalValue / $totalQuantity) : 0,
        ];
    }

    /**
     * Get current weighted average cost.
     */
    public function getCurrentAverageCost(Model $model, ?string $locationId = null): int
    {
        $valuation = $this->calculateValuation($model, $locationId);

        return $valuation['average_cost'];
    }

    /**
     * Recalculate and update all layer costs.
     */
    public function recalculate(Model $model, ?string $locationId = null): int
    {
        return DB::transaction(function () use ($model, $locationId): int {
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $valuation = $this->valuation($model, $locationId, true);

            if ($valuation['quantity'] === 0) {
                return 0;
            }

            $this->updateExistingLayers($model, $valuation['average_cost'], $locationId);

            return $valuation['average_cost'];
        });
    }

    /**
     * Check if sufficient quantity is available.
     */
    public function hasAvailableQuantity(Model $model, int $quantity, ?string $locationId = null): bool
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::WeightedAverage)
                ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
        );

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            if (! $includeNullLocation) {
                $query->whereNotNull('location_id');
            }
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = app(LocationVisibility::class)->isVisible($locationId);

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        return $query->sum('remaining_quantity') >= $quantity;
    }

    /**
     * Update existing layers with new average cost.
     */
    private function updateExistingLayers(Model $model, int $newAverageCost, ?string $locationId = null): void
    {
        $query = InventoryOwnerScope::forMutation(
            InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::WeightedAverage)
                ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
        );

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            if (! $includeNullLocation) {
                $query->whereNotNull('location_id');
            }
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = app(LocationVisibility::class)->isWritable($locationId);

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        $query->update(['unit_cost_minor' => $newAverageCost]);
    }
}

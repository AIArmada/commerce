<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Persistence;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Traceability\Api\BatchIdentity;
use AIArmada\Inventory\Features\Valuation\Models\InventoryCostLayer;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FifoCostService
{
    /**
     * Add a new FIFO cost layer.
     */
    public function addLayer(
        Model $model,
        int $quantity,
        int $unitCostMinor,
        ?string $locationId = null,
        ?string $batchId = null,
        ?string $reference = null,
        ?CarbonImmutable $layerDate = null
    ): InventoryCostLayer {
        if ($quantity <= 0 || $unitCostMinor < 0) {
            throw new InvalidArgumentException('Cost layer quantity must be positive and unit cost non-negative.');
        }

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

        return InventoryCostLayer::create([
            'inventoryable_type' => $model->getMorphClass(),
            'inventoryable_id' => $model->getKey(),
            'location_id' => $locationId,
            'batch_id' => $batchId,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost_minor' => $unitCostMinor,
            'total_cost_minor' => $quantity * $unitCostMinor,
            'currency' => config('inventory.defaults.currency', 'MYR'),
            'reference' => $reference,
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => $layerDate ?? CarbonImmutable::now(),
        ]);
    }

    /**
     * Consume quantity using FIFO ordering.
     *
     * @return array{consumed: int, cost: int, layers: array<int, array{layer_id: string, quantity: int, unit_cost: int}>}
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
            $query = InventoryOwnerScope::forMutation(
                InventoryCostLayer::query()
                    ->forModel($model)
                    ->withRemainingQuantity()
                    ->usingMethod(CostingMethod::Fifo)
                    ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
                    ->fifoOrder()
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

            $totalConsumed = 0;
            $totalCost = 0;
            $consumedLayers = [];

            foreach ($layers as $layer) {
                if ($totalConsumed >= $quantity) {
                    break;
                }

                $neededQuantity = $quantity - $totalConsumed;
                $consumed = $layer->consume($neededQuantity);

                $totalConsumed += $consumed;
                $totalCost += $consumed * $layer->unit_cost_minor;

                $consumedLayers[] = [
                    'layer_id' => $layer->id,
                    'quantity' => $consumed,
                    'unit_cost' => $layer->unit_cost_minor,
                ];
            }

            return [
                'consumed' => $totalConsumed,
                'cost' => $totalCost,
                'layers' => $consumedLayers,
            ];
        });
    }

    /**
     * Calculate FIFO valuation for a model.
     *
     * @return array{quantity: int, value: int, average_cost: int, layers: int}
     */
    public function calculateValuation(Model $model, ?string $locationId = null): array
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::Fifo)
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

        $totals = $query->selectRaw('COUNT(*) as layer_count, COALESCE(SUM(remaining_quantity), 0) as total_quantity, COALESCE(SUM(remaining_quantity * unit_cost_minor), 0) as total_value')->firstOrFail();
        $totalQuantity = (int) $totals->getAttribute('total_quantity');
        $totalValue = (int) $totals->getAttribute('total_value');
        $layerCount = (int) $totals->getAttribute('layer_count');

        return [
            'quantity' => $totalQuantity,
            'value' => $totalValue,
            'average_cost' => $totalQuantity > 0 ? (int) round($totalValue / $totalQuantity) : 0,
            'layers' => $layerCount,
        ];
    }

    /**
     * Get estimated COGS for a given quantity using FIFO.
     */
    public function estimateCogs(Model $model, int $quantity, ?string $locationId = null): int
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::Fifo)
                    ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
                ->fifoOrder()
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

        $layers = $query->get();

        $remaining = $quantity;
        $cost = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $layer->remaining_quantity);
            $cost += $take * $layer->unit_cost_minor;
            $remaining -= $take;
        }

        return $cost;
    }

    /**
     * Get all active layers for a model.
     *
     * @return Collection<int, InventoryCostLayer>
     */
    public function getActiveLayers(Model $model, ?string $locationId = null): Collection
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::Fifo)
                    ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
                ->fifoOrder()
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

        return $query->get();
    }

    /**
     * Get oldest layer for a model.
     */
    public function getOldestLayer(Model $model, ?string $locationId = null): ?InventoryCostLayer
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::Fifo)
                    ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
                ->fifoOrder()
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

        return $query->first();
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
                ->usingMethod(CostingMethod::Fifo)
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
}

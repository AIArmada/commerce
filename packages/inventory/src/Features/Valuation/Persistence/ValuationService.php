<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Persistence;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Valuation\Models\InventoryCostLayer;
use AIArmada\Inventory\Features\Valuation\Models\InventoryValuationSnapshot;
use AIArmada\Inventory\Features\Valuation\Queries\StandardStockValuesQuery;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Handles inventory valuation using different costing methods.
 *
 * Supports FIFO, Weighted Average, and Standard Cost methods for
 * accurate inventory valuation and COGS calculation.
 *
 * @example Calculate valuation using FIFO
 * ```php
 * $service = app(ValuationService::class);
 * $valuation = $service->calculateValuation(
 *     $product,
 *     CostingMethod::Fifo
 * );
 * // ['quantity' => 100, 'value' => 15000, 'average_cost' => 150]
 * ```
 * @example Get total inventory value for a warehouse
 * ```php
 * $totals = $service->getLocationValuation($warehouse->id, CostingMethod::WeightedAverage);
 * // ['total_quantity' => 5000, 'total_value' => 750000, 'sku_count' => 150]
 * ```
 * @example Create valuation snapshot for month-end
 * ```php
 * $snapshot = $service->createSnapshot(CostingMethod::Fifo, 'Monthly close - Dec 2024');
 * ```
 * @example Calculate COGS for a shipment
 * ```php
 * $cogs = $service->calculateCogs($product, 10, CostingMethod::Fifo);
 * // Uses FIFO layers to determine cost of goods sold
 * ```
 */
final class ValuationService
{
    public function __construct(
        private FifoCostingMethod $fifoCostingMethod,
        private WeightedAverageCostingMethod $weightedAverageCostingMethod,
        private StandardCostingMethod $standardCostingMethod,
    ) {}

    /**
     * Calculate valuation for a model using specified costing method.
     *
     * @return array{quantity: int, value: int, average_cost: int}
     */
    public function calculateValuation(
        Model $model,
        CostingMethod $method,
        ?string $locationId = null
    ): array {
        return match ($method) {
            CostingMethod::Fifo => $this->fifoCostingMethod->calculateValuation($model, $locationId),
            CostingMethod::WeightedAverage => $this->weightedAverageCostingMethod->calculateValuation($model, $locationId),
            CostingMethod::Standard => $this->standardCostingMethod->calculateValuation($model, $locationId),
            default => $this->fifoCostingMethod->calculateValuation($model, $locationId),
        };
    }

    /**
     * Get total inventory valuation for a location.
     *
     * @return array{total_quantity: int, total_value: int, sku_count: int}
     */
    public function getLocationValuation(string $locationId, CostingMethod $method): array
    {
        $this->assertLocationVisible($locationId);
        $totals = $this->summarize($method, $locationId);

        return [
            'total_quantity' => $totals['total_quantity'],
            'total_value' => $totals['total_value'],
            'sku_count' => $totals['sku_count'],
        ];
    }

    /**
     * Get total inventory valuation across all locations.
     *
     * @return array{total_quantity: int, total_value: int, sku_count: int, location_count: int}
     */
    public function getTotalValuation(CostingMethod $method): array
    {
        $totals = $this->summarize($method);

        return [
            'total_quantity' => $totals['total_quantity'],
            'total_value' => $totals['total_value'],
            'sku_count' => $totals['sku_count'],
            'location_count' => $totals['location_count'],
        ];
    }

    /**
     * Create a valuation snapshot.
     */
    public function createSnapshot(
        CostingMethod $method,
        ?string $locationId = null,
        ?CarbonImmutable $snapshotDate = null
    ): InventoryValuationSnapshot {
        $snapshotDate = $snapshotDate ?? today();

        if ($locationId !== null && ! app(LocationVisibility::class)->isWritable($locationId)) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }

        $valuation = $this->summarize($method, $locationId, true);
        $previousQuery = InventoryOwnerScope::forMutation(InventoryValuationSnapshot::query())
            ->usingMethod($method)
            ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
            ->where('location_id', $locationId)
            ->whereDate('snapshot_date', '<=', $snapshotDate)
            ->latestBySnapshotDate()
            ->orderByDesc('id');
        $previousSnapshot = $previousQuery->first();
        $variance = $previousSnapshot !== null
            ? $valuation['total_value'] - $previousSnapshot->total_value_minor
            : null;

        $owner = InventoryOwnerScope::resolveOwner();

        $snapshot = new InventoryValuationSnapshot;
        $snapshot->forceFill([
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
            'location_id' => $locationId,
            'costing_method' => $method,
            'snapshot_date' => $snapshotDate,
            'total_quantity' => $valuation['total_quantity'],
            'total_value_minor' => $valuation['total_value'],
            'average_unit_cost_minor' => $valuation['total_quantity'] > 0
                ? (int) round($valuation['total_value'] / $valuation['total_quantity'])
                : 0,
            'currency' => config('inventory.defaults.currency', 'MYR'),
            'sku_count' => $valuation['sku_count'],
            'variance_from_previous_minor' => $variance,
            'breakdown' => $valuation['breakdown'],
        ])->save();

        return $snapshot;
    }

    /**
     * Get latest snapshot.
     */
    public function getLatestSnapshot(CostingMethod $method, ?string $locationId = null): ?InventoryValuationSnapshot
    {
        $query = InventoryValuationSnapshot::query()
            ->usingMethod($method)
            ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
            ->latestBySnapshotDate()
            ->orderByDesc('id');

        if (InventoryOwnerScope::isEnabled()) {
            $query->forOwner(InventoryOwnerScope::resolveOwner(), InventoryOwnerScope::includeGlobal());
        }

        if ($locationId !== null) {
            $this->assertLocationVisible($locationId);
            $query->forLocation($locationId);
        } else {
            $query->allLocations();
        }

        return $query->first();
    }

    /**
     * Get snapshots for a date range.
     *
     * @return Collection<int, InventoryValuationSnapshot>
     */
    public function getSnapshotsForRange(
        CostingMethod $method,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $locationId = null
    ): Collection {
        $query = InventoryValuationSnapshot::query()
            ->usingMethod($method)
            ->where('currency', (string) config('inventory.defaults.currency', 'MYR'))
            ->betweenDates($from, $to)
            ->orderBy('snapshot_date');

        if (InventoryOwnerScope::isEnabled()) {
            $query->forOwner(InventoryOwnerScope::resolveOwner(), InventoryOwnerScope::includeGlobal());
        }

        if ($locationId !== null) {
            $this->assertLocationVisible($locationId);
            $query->forLocation($locationId);
        } else {
            $query->allLocations();
        }

        return $query->get();
    }

    /**
     * Compare valuations between two methods.
     *
     * @return array{method1: array{value: int, average: int}, method2: array{value: int, average: int}, difference: int}
     */
    public function compareValuations(
        Model $model,
        CostingMethod $method1,
        CostingMethod $method2,
        ?string $locationId = null
    ): array {
        $valuation1 = $this->calculateValuation($model, $method1, $locationId);
        $valuation2 = $this->calculateValuation($model, $method2, $locationId);

        return [
            'method1' => [
                'value' => $valuation1['value'],
                'average' => $valuation1['average_cost'],
            ],
            'method2' => [
                'value' => $valuation2['value'],
                'average' => $valuation2['average_cost'],
            ],
            'difference' => $valuation1['value'] - $valuation2['value'],
        ];
    }

    /**
     * Generate valuation report for all locations.
     *
     * @return array<string, array{location_name: string, quantity: int, value: int, average_cost: int}>
     */
    public function generateLocationReport(CostingMethod $method): array
    {
        $locations = app(LocationVisibility::class)->activeLocations();

        $report = [];

        foreach ($locations as $location) {
            $valuation = $this->getLocationValuation($location['id'], $method);

            $report[$location['id']] = [
                'location_name' => $location['name'],
                'quantity' => $valuation['total_quantity'],
                'value' => $valuation['total_value'],
                'average_cost' => $valuation['total_quantity'] > 0
                    ? (int) round($valuation['total_value'] / $valuation['total_quantity'])
                    : 0,
            ];
        }

        return $report;
    }

    /**
     * Create daily snapshots for all locations.
     *
     * @return Collection<int, InventoryValuationSnapshot>
     */
    public function createDailySnapshots(CostingMethod $method): Collection
    {
        return DB::transaction(function () use ($method): Collection {
            $snapshots = new Collection;

            $snapshots->push($this->createSnapshot($method));

            $locations = app(LocationVisibility::class)->activeLocations(exactOwner: true);

            foreach ($locations as $location) {
                $snapshots->push($this->createSnapshot($method, $location['id']));
            }

            return $snapshots;
        });
    }

    /**
     * @return array{total_quantity: int, total_value: int, sku_count: int, location_count: int, breakdown: array<string, array{units: int, value: int}>}
     */
    private function summarize(CostingMethod $method, ?string $locationId = null, bool $exactOwner = false): array
    {
        $currency = (string) config('inventory.defaults.currency', 'MYR');

        if ($method === CostingMethod::Standard) {
            $rows = app(StandardStockValuesQuery::class)->rows($currency, locationId: $locationId, exactOwner: $exactOwner);
        } else {
            $query = $exactOwner
                ? InventoryOwnerScope::forMutation(InventoryCostLayer::query())
                : InventoryOwnerScope::applyToLocationQuery(InventoryCostLayer::query());

            $query->withRemainingQuantity()->usingMethod($method)->where('currency', $currency);

            if ($locationId !== null) {
                $query->where('location_id', $locationId);
            }

            $rows = $query->toBase()
                ->select('inventoryable_type', 'inventoryable_id', 'location_id')
                ->selectRaw('SUM(remaining_quantity) AS quantity, SUM(remaining_quantity * unit_cost_minor) AS total_value')
                ->groupBy('inventoryable_type', 'inventoryable_id', 'location_id')
                ->cursor()
                ->map(static fn ($row): array => [
                    'inventoryable_type' => (string) $row->inventoryable_type,
                    'inventoryable_id' => (string) $row->inventoryable_id,
                    'location_id' => $row->location_id === null ? null : (string) $row->location_id,
                    'quantity' => (int) $row->quantity,
                    'total_value' => (int) $row->total_value,
                ]);
        }

        $quantity = 0;
        $value = 0;
        $skus = [];
        $locations = [];
        $breakdown = [];

        foreach ($rows as $row) {
            $type = $row['inventoryable_type'];
            $quantity += $row['quantity'];
            $value += $row['total_value'];
            $skus[$type][$row['inventoryable_id']] = true;

            if ($row['location_id'] !== null) {
                $locations[$row['location_id']] = true;
            }

            $breakdown[$type] ??= ['units' => 0, 'value' => 0];
            $breakdown[$type]['units'] += $row['quantity'];
            $breakdown[$type]['value'] += $row['total_value'];
        }

        return [
            'total_quantity' => $quantity,
            'total_value' => $value,
            'sku_count' => array_sum(array_map(count(...), $skus)),
            'location_count' => count($locations),
            'breakdown' => $breakdown,
        ];
    }

    private function assertLocationVisible(string $locationId): void
    {
        if (! app(LocationVisibility::class)->isVisible($locationId)) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }
    }
}

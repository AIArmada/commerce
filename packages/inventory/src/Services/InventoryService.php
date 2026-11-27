<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Actions\AdjustInventory;
use AIArmada\Inventory\Actions\ReceiveInventory;
use AIArmada\Inventory\Actions\ShipInventory;
use AIArmada\Inventory\Actions\TransferInventory;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Persistence\InventoryLevelStore;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Core inventory operations service.
 *
 * Handles receiving, shipping, transferring, and adjusting inventory
 * with full movement tracking and event dispatching.
 *
 * @example Receiving inventory
 * ```php
 * $service = app(InventoryService::class);
 * $movement = $service->receive($product, $location->id, 100, reason: 'PO-2024-001');
 * ```
 * @example Shipping inventory
 * ```php
 * $movement = $service->ship($product, $location->id, 10, reason: 'ORD-2024-001');
 * ```
 * @example Checking availability
 * ```php
 * $availability = $service->getAvailability($product);
 * // ['total' => 100, 'reserved' => 20, 'available' => 80, 'locations' => [...]]
 * ```
 */
final class InventoryService
{
    /**
     * Receive inventory at a location.
     *
     * Creates an inventory level record if one doesn't exist, increments
     * on-hand quantity, and records the movement for audit purposes.
     *
     * @param  Model  $model  The inventoryable model (e.g., Product)
     * @param  string  $locationId  UUID of the receiving location
     * @param  int  $quantity  Positive quantity to receive
     * @param  string|null  $reason  Reference number (e.g., PO number)
     * @param  string|null  $note  Additional notes
     * @param  string|null  $userId  ID of user performing the action
     * @param  DateTimeInterface|null  $occurredAt  When the receipt occurred (defaults to now)
     * @return InventoryMovement The created movement record
     *
     * @throws \InvalidArgumentException If quantity is not positive
     */
    public function receive(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        return app(ReceiveInventory::class)->handle($model, $locationId, $quantity, $reason, $note, $userId, $occurredAt);
    }

    /**
     * Ship inventory from a location.
     */
    public function ship(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        return app(ShipInventory::class)->handle($model, $locationId, $quantity, $reason, $reference, $note, $userId, $occurredAt);
    }

    /**
     * Transfer inventory between locations.
     */
    public function transfer(
        Model $model,
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        return app(TransferInventory::class)->handle($model, $fromLocationId, $toLocationId, $quantity, $note, $userId, $occurredAt);
    }

    /**
     * Adjust inventory to a specific quantity at a location.
     */
    public function adjust(
        Model $model,
        string $locationId,
        int $newQuantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        return app(AdjustInventory::class)->handle($model, $locationId, $newQuantity, $reason, $note, $userId, $occurredAt);
    }

    /**
     * Get availability across all locations.
     * Availability is read directly from committed inventory levels.
     *
     * @return array<string, int> Location ID => available quantity
     */
    public function getAvailability(Model $model): array
    {
        $rows = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
        )
            ->select('location_id')
            ->selectRaw($this->availableAggregateExpression() . ' as available_total')
            ->groupBy('location_id')
            ->get();

        $availability = [];

        foreach ($rows as $row) {
            $availability[$row->location_id] = (int) $row->getAttribute('available_total');
        }

        return $availability;
    }

    /**
     * Get total available quantity across all locations.
     */
    public function getTotalAvailable(Model $model): int
    {
        $total = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
        )->sum(DB::raw($this->availablePerLevelExpression()));

        return (int) $total;
    }

    /**
     * Get total available quantities for many models with one query.
     *
     * Powers per-variant fan-out reads (product pages, carts) without the
     * N+1 cost of calling getTotalAvailable() per variant. Models without
     * any active-location level are reported as zero.
     *
     * @param  iterable<int, Model>  $models
     * @return array<string, int> Keyed by "{morph-class}:{key}".
     */
    public function getAvailabilityForMany(iterable $models): array
    {
        $pairs = [];

        foreach ($models as $model) {
            $pairs[$model->getMorphClass() . ':' . $model->getKey()] = [
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
            ];
        }

        $totals = array_fill_keys(array_keys($pairs), 0);

        if ($pairs === []) {
            return $totals;
        }

        $byType = [];

        foreach ($pairs as $pair) {
            $byType[$pair['inventoryable_type']][] = $pair['inventoryable_id'];
        }

        $rows = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where(function (Builder $query) use ($byType): void {
                    foreach ($byType as $type => $ids) {
                        $query->orWhere(function (Builder $group) use ($type, $ids): void {
                            $group->where('inventoryable_type', $type)
                                ->whereIn('inventoryable_id', $ids);
                        });
                    }
                })
                ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
        )
            ->select('inventoryable_type', 'inventoryable_id')
            ->selectRaw($this->availableAggregateExpression() . ' as available_total')
            ->groupBy('inventoryable_type', 'inventoryable_id')
            ->get();

        foreach ($rows as $row) {
            $key = $row->inventoryable_type . ':' . $row->inventoryable_id;

            if (array_key_exists($key, $totals)) {
                $totals[$key] = (int) $row->getAttribute('available_total');
            }
        }

        return $totals;
    }

    /**
     * Per-level available quantity, mirroring InventoryLevel::available
     * (never negative) in portable SQL.
     */
    private function availablePerLevelExpression(): string
    {
        return 'CASE WHEN quantity_on_hand > quantity_reserved THEN quantity_on_hand - quantity_reserved ELSE 0 END';
    }

    private function availableAggregateExpression(): string
    {
        return 'SUM(' . $this->availablePerLevelExpression() . ')';
    }

    /**
     * Get total on-hand quantity across all locations.
     */
    public function getTotalOnHand(Model $model): int
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $locationQuery): Builder => $locationQuery->where('is_active', true))
        );

        return (int) $query->sum('quantity_on_hand');
    }

    /**
     * Check if sufficient inventory exists.
     */
    public function hasInventory(Model $model, int $quantity): bool
    {
        return $this->getTotalAvailable($model) >= $quantity;
    }

    /**
     * Get inventory level at a specific location.
     */
    public function getLevel(Model $model, string $locationId): ?InventoryLevel
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->where('location_id', $locationId)
        );

        return $query->first();
    }

    /**
     * Get or create inventory level at a location.
     */
    public function getOrCreateLevel(Model $model, string $locationId): InventoryLevel
    {
        return app(InventoryLevelStore::class)->getOrCreate($model, $locationId);
    }

    /**
     * Get movement history for a model.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getMovementHistory(Model $model, int $limit = 50): Collection
    {
        $query = InventoryMovement::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());

        InventoryOwnerScope::applyToMovementQuery($query);

        return $query
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Receive inventory at the default location (for simple setups).
     */
    public function receiveAtDefault(
        Model $model,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        $location = InventoryLocation::getOrCreateDefault();

        return $this->receive($model, $location->id, $quantity, $reason, $note, $userId);
    }

    /**
     * Ship inventory from the default location (for simple setups).
     */
    public function shipFromDefault(
        Model $model,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        $location = InventoryLocation::getOrCreateDefault();

        return $this->ship($model, $location->id, $quantity, $reason, $reference, $note, $userId);
    }
}

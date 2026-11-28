<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Traits;

use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Stock\Queries\InventoryStockQuery;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasInventory
{
    /**
     * Get all inventory levels for the model.
     *
     * @return MorphMany<InventoryLevel, $this>
     */
    public function inventoryLevels(): MorphMany
    {
        $relation = $this->morphMany(InventoryLevel::class, 'inventoryable');

        InventoryOwnerScope::applyToLocationQuery($relation->getQuery())->orderByDesc('quantity_on_hand');

        return $relation;
    }

    /**
     * Get all inventory movements for the model.
     *
     * @return MorphMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): MorphMany
    {
        $relation = $this->morphMany(InventoryMovement::class, 'inventoryable');

        InventoryOwnerScope::applyToMovementQuery($relation->getQuery())->orderByDesc('occurred_at');

        return $relation;
    }

    /**
     * Get total quantity on hand across all locations.
     */
    public function getTotalOnHand(): int
    {
        return app(InventoryStockQuery::class)->getTotalOnHand($this);
    }

    /**
     * Get total available quantity across all locations.
     */
    public function getTotalAvailable(): int
    {
        return app(InventoryStockQuery::class)->getTotalAvailable($this);
    }

    /**
     * Check if sufficient inventory exists across all locations.
     */
    public function hasInventory(int $quantity = 1): bool
    {
        return app(InventoryStockQuery::class)->hasInventory($this, $quantity);
    }

    /**
     * Get inventory level at a specific location.
     */
    public function getInventoryAtLocation(string $locationId): ?InventoryLevel
    {
        return app(InventoryStockQuery::class)->getLevel($this, $locationId);
    }

    /**
     * Get availability across all locations.
     *
     * @return array<string, int> Location ID => available quantity
     */
    public function getAvailability(): array
    {
        return app(InventoryStockQuery::class)->getAvailability($this);
    }

    /**
     * Get the allocation strategy for this product.
     * Override in model to set per-product strategy.
     */
    public function getAllocationStrategy(): ?AllocationStrategy
    {
        return null; // Use global config by default
    }

    /**
     * Get movement history.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getInventoryHistory(int $limit = 50): Collection
    {
        return app(InventoryStockQuery::class)->getMovementHistory($this, $limit);
    }

    /**
     * Check if inventory is low at any location.
     */
    public function isLowInventory(?int $threshold = null): bool
    {
        $threshold ??= (int) config('inventory.default_reorder_point', 10);

        return app(InventoryStockQuery::class)->isLowInventory($this, $threshold);
    }
}

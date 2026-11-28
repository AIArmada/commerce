<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Traits;

use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
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
        return $this->getInventoryService()->getTotalOnHand($this);
    }

    /**
     * Get total available quantity across all locations.
     */
    public function getTotalAvailable(): int
    {
        return $this->getInventoryService()->getTotalAvailable($this);
    }

    /**
     * Check if sufficient inventory exists across all locations.
     */
    public function hasInventory(int $quantity = 1): bool
    {
        return $this->getInventoryService()->hasInventory($this, $quantity);
    }

    /**
     * Get inventory level at a specific location.
     */
    public function getInventoryAtLocation(string $locationId): ?InventoryLevel
    {
        return $this->getInventoryService()->getLevel($this, $locationId);
    }

    /**
     * Get availability across all locations.
     *
     * @return array<string, int> Location ID => available quantity
     */
    public function getAvailability(): array
    {
        return $this->getInventoryService()->getAvailability($this);
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
        return $this->getInventoryService()->getMovementHistory($this, $limit);
    }

    /**
     * Check if inventory is low at any location.
     */
    public function isLowInventory(?int $threshold = null): bool
    {
        $threshold ??= (int) config('inventory.default_reorder_point', 10);

        return $threshold >= 0 && $this->inventoryLevels()
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= ?', [$threshold])
            ->exists();
    }

    /**
     * Get the inventory service instance.
     */
    protected function getInventoryService(): InventoryService
    {
        return app(InventoryService::class);
    }
}

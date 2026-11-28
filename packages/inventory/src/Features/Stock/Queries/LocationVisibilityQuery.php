<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;

final class LocationVisibilityQuery implements LocationVisibility
{
    public function isVisible(string $locationId): bool
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->whereKey($locationId)
            ->exists();
    }

    public function isWritable(string $locationId): bool
    {
        return InventoryOwnerScope::forMutation(InventoryLocation::query())
            ->whereKey($locationId)
            ->exists();
    }

    public function activeLocations(bool $exactOwner = false): iterable
    {
        $query = $exactOwner
            ? InventoryOwnerScope::forMutation(InventoryLocation::query())
            : InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());

        foreach ($query->where('is_active', true)->select(['id', 'name'])->lazyById(200) as $location) {
            yield ['id' => $location->id, 'name' => $location->name];
        }
    }
}

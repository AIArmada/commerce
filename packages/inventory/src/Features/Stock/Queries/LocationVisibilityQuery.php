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
}

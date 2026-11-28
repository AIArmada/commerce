<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\LocationVisibility;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class LocationVisibilityQuery implements LocationVisibility
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

    public function lockWritable(string $locationId, bool $activeOnly = false): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Writable location locking requires an active transaction.');
        }

        $query = InventoryOwnerScope::forMutation(InventoryLocation::query())->whereKey($locationId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->lockForUpdate()->first(['id']) !== null;
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

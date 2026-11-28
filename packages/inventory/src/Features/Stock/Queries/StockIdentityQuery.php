<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\StockIdentity;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;

final readonly class StockIdentityQuery implements StockIdentity
{
    public function matchesWritableLevel(string $levelId, string $type, string $id, string $locationId): bool
    {
        return InventoryOwnerScope::forMutation(InventoryLevel::query())
            ->whereKey($levelId)
            ->where('inventoryable_type', $type)
            ->where('inventoryable_id', $id)
            ->where('location_id', $locationId)
            ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::forMutation($query))
            ->exists();
    }
}

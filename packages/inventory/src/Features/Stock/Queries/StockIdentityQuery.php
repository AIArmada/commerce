<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Queries;

use AIArmada\Inventory\Features\Stock\Api\StockIdentity;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class StockIdentityQuery implements StockIdentity
{
    public function lockWritableLevel(string $levelId): ?array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Stock identity locking requires an active transaction.');
        }

        $level = InventoryOwnerScope::forMutation(InventoryLevel::query())
            ->whereKey($levelId)
            ->whereHas('location', fn (Builder $query): Builder => InventoryOwnerScope::forMutation($query))
            ->lockForUpdate()
            ->first();

        if ($level === null) {
            return null;
        }

        return [
            'type' => $level->inventoryable_type,
            'id' => (string) $level->inventoryable_id,
            'location_id' => $level->location_id,
        ];
    }

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

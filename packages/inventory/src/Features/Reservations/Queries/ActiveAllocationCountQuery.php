<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Queries;

use AIArmada\Inventory\Features\Reservations\Api\ActiveAllocationCount;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;

final readonly class ActiveAllocationCountQuery implements ActiveAllocationCount
{
    public function count(): int
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryAllocation::query()->active())->count();
    }
}

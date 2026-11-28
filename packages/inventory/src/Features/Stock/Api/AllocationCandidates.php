<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

use AIArmada\Inventory\Enums\AllocationStrategy;

interface AllocationCandidates
{
    /** @return list<array{id: string, location_id: string, available: int}> */
    public function locked(string $inventoryableType, string $inventoryableId, AllocationStrategy $strategy): array;
}

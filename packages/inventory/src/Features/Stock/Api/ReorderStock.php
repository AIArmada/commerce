<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface ReorderStock
{
    /** @return iterable<ReorderStockLevel> */
    public function candidates(?string $locationId = null): iterable;

    public function locked(string $levelId): ReorderStockLevel;
}

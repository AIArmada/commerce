<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface StockIdentity
{
    public function matchesWritableLevel(string $levelId, string $type, string $id, string $locationId): bool;
}

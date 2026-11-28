<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface StockBalances
{
    /**
     * @param  list<array{inventoryable_type: string, inventoryable_id: string, location_id: string}>|null  $items
     * @return iterable<array{inventoryable_type: string, inventoryable_id: string, location_id: string, owner_type: string|null, owner_id: string|null, quantity: int}>
     */
    public function rows(?array $items = null): iterable;
}

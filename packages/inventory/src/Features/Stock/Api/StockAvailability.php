<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface StockAvailability
{
    /**
     * @param  list<array{type: string, id: string}>  $items
     * @return iterable<array{type: string, id: string, location_id: string, available: int}>
     */
    public function byItem(array $items): iterable;
}

<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface StockHealth
{
    /** @return array{low_stock: int, out_of_stock: int} */
    public function counts(int $threshold): array;
}

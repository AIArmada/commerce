<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

use Carbon\CarbonImmutable;

interface StockExportRows
{
    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, mixed>>
     */
    public function levels(array $filters): iterable;

    /** @return iterable<int, array<int, mixed>> */
    public function movements(CarbonImmutable $startDate, CarbonImmutable $endDate, ?string $movementType, ?string $locationId): iterable;
}

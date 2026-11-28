<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

use Carbon\CarbonImmutable;

interface StockKpiMetrics
{
    /** @return array{quantity: int, total: int, fulfilled: int} */
    public function shipments(CarbonImmutable $start, CarbonImmutable $end, ?string $type = null, ?string $id = null): array;

    public function onHand(?string $type = null, ?string $id = null): int;

    /** @return array{total: int, accurate: int} */
    public function cycleCounts(CarbonImmutable $start, CarbonImmutable $end): array;

    /** @return array{total_skus: int, low_stock: int, out_of_stock: int} */
    public function skuCounts(int $threshold): array;

    public function depletionDays(string $type, string $id, ?string $locationId, CarbonImmutable $start, CarbonImmutable $end): int;
}

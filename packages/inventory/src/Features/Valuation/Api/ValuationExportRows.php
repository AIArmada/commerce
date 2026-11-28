<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Api;

use Carbon\CarbonImmutable;

interface ValuationExportRows
{
    /** @return iterable<int, array<int, mixed>> */
    public function rows(CarbonImmutable $startDate, CarbonImmutable $endDate): iterable;
}

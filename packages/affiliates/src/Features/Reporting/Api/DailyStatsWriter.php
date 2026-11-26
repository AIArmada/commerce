<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Api;

use Carbon\CarbonImmutable;

interface DailyStatsWriter
{
    public function aggregate(CarbonImmutable $date): int;

    /** @return list<DailyStatRecord> */
    public function aggregateForAffiliate(string $affiliateId, CarbonImmutable $date): array;

    public function aggregateRange(CarbonImmutable $from, CarbonImmutable $to): int;

    public function deleteForAffiliate(string $affiliateId): void;
}

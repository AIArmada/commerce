<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Api;

use Carbon\CarbonImmutable;

interface DailyStatsReader
{
    /** @return array{clicks: int, unique_clicks: int, attributions: int, conversions: int, revenue_cents: int|null, commission_cents: int|null, currency: string, converted: bool, by_currency: array<string, array{conversions: int, revenue_cents: int, commission_cents: int}>, conversion_rate: float|null, epc_cents: float|null} */
    public function getAggregatedStats(string $affiliateId, CarbonImmutable $from, CarbonImmutable $to): array;
}

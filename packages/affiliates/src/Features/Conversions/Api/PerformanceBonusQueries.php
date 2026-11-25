<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use Carbon\CarbonImmutable;

interface PerformanceBonusQueries
{
    /** @return array<array-key, array<string, mixed>> */
    public function calculateBonuses(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array;

    /** @return list<array{rank: int, affiliate_id: string, affiliate_name: string, affiliate_code: string, total_revenue: int, total_conversions: int, total_commissions: int, avg_order_value: float}> */
    public function getLeaderboard(?CarbonImmutable $from = null, ?CarbonImmutable $to = null, int $limit = 10): array;
}

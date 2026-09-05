<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DailyStatsAggregated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly int $affiliateCount
    ) {}
}

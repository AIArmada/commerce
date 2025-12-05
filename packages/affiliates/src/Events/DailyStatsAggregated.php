<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

final class DailyStatsAggregated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Carbon $date,
        public readonly int $affiliateCount
    ) {}
}

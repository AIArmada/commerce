<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Actions;

use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;

final class DeleteAffiliateDailyStats
{
    public function execute(string $affiliateId): void
    {
        AffiliateDailyStat::query()->where('affiliate_id', $affiliateId)->delete();
    }
}

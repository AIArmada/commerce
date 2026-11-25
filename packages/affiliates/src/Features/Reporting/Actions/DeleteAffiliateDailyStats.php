<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Actions;

use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\Affiliate;
use LogicException;

final class DeleteAffiliateDailyStats
{
    public function execute(string $affiliateId): void
    {
        if ((new AffiliateDailyStat)->getConnection() !== (new Affiliate)->getConnection()) {
            throw new LogicException('Affiliate statistics cleanup requires a shared database connection.');
        }

        Affiliate::query()->forOwner()->findOrFail($affiliateId);
        AffiliateDailyStat::query()->where('affiliate_id', $affiliateId)->delete();
    }
}

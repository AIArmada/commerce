<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Reporting\Actions;

use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;

final class DeleteAffiliateDailyStats
{
    public function __construct(private readonly AffiliateAccessGuard $affiliates) {}

    public function execute(string $affiliateId): void
    {
        $this->affiliates->assertCanWrite($affiliateId, spl_object_id((new AffiliateDailyStat)->getConnection()));
        AffiliateDailyStat::query()->where('affiliate_id', $affiliateId)->delete();
    }
}

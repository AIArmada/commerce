<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Models\ReferralReward;

final class NullReferralAffiliateResolver implements ReferralAffiliateResolver
{
    public function resolve(ReferralReward $reward): ?object
    {
        return null;
    }
}

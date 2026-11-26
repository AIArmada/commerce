<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use AIArmada\Affiliates\Data\AwardRecipient;
use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Models\ReferralReward;

final class NullReferralAffiliateResolver implements ReferralAffiliateResolver
{
    public function resolve(ReferralReward $reward): ?AwardRecipient
    {
        return null;
    }
}

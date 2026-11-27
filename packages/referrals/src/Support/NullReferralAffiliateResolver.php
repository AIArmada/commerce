<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use AIArmada\Affiliates\Data\AwardRecipient;
use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Data\ReferralRewardContext;

final class NullReferralAffiliateResolver implements ReferralAffiliateResolver
{
    public function resolve(ReferralRewardContext $reward): ?AwardRecipient
    {
        return null;
    }
}

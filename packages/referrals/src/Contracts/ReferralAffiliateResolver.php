<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Contracts;

use AIArmada\Affiliates\Data\AwardRecipient;
use AIArmada\Referrals\Data\ReferralRewardContext;

interface ReferralAffiliateResolver
{
    public function resolve(ReferralRewardContext $reward): ?AwardRecipient;
}

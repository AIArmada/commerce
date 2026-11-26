<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Contracts;

use AIArmada\Affiliates\Data\AwardRecipient;
use AIArmada\Referrals\Models\ReferralReward;

interface ReferralAffiliateResolver
{
    public function resolve(ReferralReward $reward): ?AwardRecipient;
}

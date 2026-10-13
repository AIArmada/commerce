<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Contracts;

use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Models\ReferralReward;

interface ReferralRewardFulfillment
{
    public function fulfill(ReferralReward $reward): ReferralFulfillmentResult;

    public function reverse(ReferralReward $reward, string $reason): ReferralFulfillmentResult;
}

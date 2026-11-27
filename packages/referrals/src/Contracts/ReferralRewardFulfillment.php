<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Contracts;

use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Data\ReferralRewardContext;

interface ReferralRewardFulfillment
{
    public function fulfill(ReferralRewardContext $reward): ReferralFulfillmentResult;

    public function reverse(ReferralRewardContext $reward, string $reason): ReferralFulfillmentResult;
}

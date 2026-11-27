<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Drivers;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Data\ReferralRewardContext;

final class ManualReferralFulfillment implements ReferralRewardFulfillment
{
    public function fulfill(ReferralRewardContext $reward): ReferralFulfillmentResult
    {
        $reference = $reward->manualReference === null ? '' : mb_trim($reward->manualReference);

        if ($reference === '' || mb_strlen($reference) > 255) {
            return ReferralFulfillmentResult::failed('Manual fulfilment requires an external_reference of 1 to 255 characters.');
        }

        return ReferralFulfillmentResult::fulfilled($reference);
    }

    public function reverse(ReferralRewardContext $reward, string $reason): ReferralFulfillmentResult
    {
        if (! is_string($reward->externalReference) || mb_trim($reward->externalReference) === '') {
            return ReferralFulfillmentResult::failed('Manual reversal requires a stored external reference.');
        }

        return ReferralFulfillmentResult::fulfilled($reward->externalReference);
    }
}

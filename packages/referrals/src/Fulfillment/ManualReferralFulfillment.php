<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Fulfillment;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Models\ReferralReward;

final class ManualReferralFulfillment implements ReferralRewardFulfillment
{
    public function fulfill(ReferralReward $reward): ReferralFulfillmentResult
    {
        $metadataKey = (string) config('referrals.fulfillment.drivers.manual.reference_metadata_key', 'external_reference');
        $metadata = is_array($reward->metadata) ? $reward->metadata : [];
        $reference = $metadata[$metadataKey] ?? null;

        if (! is_string($reference) || mb_trim($reference) === '') {
            return ReferralFulfillmentResult::failed(sprintf('Manual fulfilment requires a non-empty [%s] reward metadata value.', $metadataKey));
        }

        return ReferralFulfillmentResult::fulfilled(mb_trim($reference));
    }

    public function reverse(ReferralReward $reward, string $reason): ReferralFulfillmentResult
    {
        if (! is_string($reward->external_reference) || mb_trim($reward->external_reference) === '') {
            return ReferralFulfillmentResult::failed('Manual reversal requires a stored external reference.');
        }

        return ReferralFulfillmentResult::fulfilled($reward->external_reference);
    }
}

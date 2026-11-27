<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Drivers;

use AIArmada\Affiliates\Data\AwardRecipient;
use AIArmada\Affiliates\Features\Conversions\Api\AwardPosting;
use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Data\ReferralRewardContext;
use Throwable;

final class AffiliatesCashFulfillment implements ReferralRewardFulfillment
{
    public function __construct(private readonly ReferralAffiliateResolver $affiliateResolver) {}

    public function fulfill(ReferralRewardContext $reward): ReferralFulfillmentResult
    {
        if (! class_exists(AwardRecipient::class) || ! interface_exists(AwardPosting::class)) {
            return ReferralFulfillmentResult::failed('The affiliates award API is unavailable.');
        }

        if ($reward->amountMinor === null || $reward->amountMinor < 1 || $reward->currency === null) {
            return ReferralFulfillmentResult::failed('Affiliate cash rewards require a positive amount and explicit currency.');
        }

        $affiliate = $this->affiliateResolver->resolve($reward);

        if ($affiliate === null || $affiliate->id === '') {
            return ReferralFulfillmentResult::failed('No affiliate recipient is configured for this referral beneficiary.');
        }

        $inviteId = (string) $reward->inviteId;
        $side = $reward->side;
        $awardKey = 'referrals:award:' . $inviteId . ':' . $side;

        try {
            $action = app(AwardPosting::class);
            $result = $action->post(
                $affiliate->id,
                $awardKey,
                $reward->amountMinor,
                $reward->currency,
                [
                    'origin' => 'referrals',
                    'source_ref' => $inviteId,
                    'external_reference' => (string) $reward->qualificationBusinessKey,
                ],
            );
        } catch (Throwable) {
            return ReferralFulfillmentResult::failed('Affiliate award failed.');
        }

        if (! $result->isCreated() && ! $result->isReplayed()) {
            return ReferralFulfillmentResult::failed('Affiliate award was refused with disposition [' . $result->disposition->value . '].');
        }

        $conversion = $result->conversion ?? null;
        $conversionId = is_object($conversion) && isset($conversion->id) ? (string) $conversion->id : null;

        if ($conversionId === null || $conversionId === '') {
            return ReferralFulfillmentResult::failed('The affiliates award result did not include a conversion id.');
        }

        return ReferralFulfillmentResult::fulfilled($conversionId);
    }

    public function reverse(ReferralRewardContext $reward, string $reason): ReferralFulfillmentResult
    {
        $conversionId = $reward->externalReference;

        if (! is_string($conversionId) || $conversionId === '' || ! interface_exists(AwardPosting::class)) {
            return ReferralFulfillmentResult::failed('Affiliate reversal requires the stored conversion id and affiliates package.');
        }

        $inviteId = (string) $reward->inviteId;
        $side = $reward->side;
        $awardKey = 'referrals:award:' . $inviteId . ':' . $side;

        try {
            $action = app(AwardPosting::class);
            $action->reverse($conversionId, $awardKey, $reason);
        } catch (Throwable) {
            return ReferralFulfillmentResult::failed('Affiliate reversal failed.');
        }

        return ReferralFulfillmentResult::fulfilled($conversionId);
    }
}

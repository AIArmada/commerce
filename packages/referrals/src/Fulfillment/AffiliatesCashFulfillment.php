<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Fulfillment;

use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Models\ReferralReward;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class AffiliatesCashFulfillment implements ReferralRewardFulfillment
{
    public function __construct(private readonly ReferralAffiliateResolver $affiliateResolver) {}

    public function fulfill(ReferralReward $reward): ReferralFulfillmentResult
    {
        $affiliateClass = 'AIArmada\\Affiliates\\Models\\Affiliate';
        $actionClass = 'AIArmada\\Affiliates\\Actions\\Conversions\\RecordAffiliateAward';

        if (! class_exists($affiliateClass) || ! class_exists($actionClass)) {
            return ReferralFulfillmentResult::failed('The affiliates award API is unavailable.');
        }

        if ($reward->amount_minor === null || $reward->amount_minor < 1 || $reward->currency === null) {
            return ReferralFulfillmentResult::failed('Affiliate cash rewards require a positive amount and explicit currency.');
        }

        $affiliate = $this->affiliateResolver->resolve($reward);

        if (! is_object($affiliate) || ! is_a($affiliate, $affiliateClass)) {
            return ReferralFulfillmentResult::failed('No affiliate recipient is configured for this referral beneficiary.');
        }

        $inviteId = (string) $reward->invite_id;
        $side = $reward->side instanceof ReferralRewardSide ? $reward->side->value : (string) $reward->side;
        $awardKey = 'referrals:award:' . $inviteId . ':' . $side;

        try {
            $action = app($actionClass);
            $result = $action->handle(
                $affiliate,
                $awardKey,
                $reward->amount_minor,
                $reward->currency,
                [
                    'origin' => 'referrals',
                    'source_ref' => $inviteId,
                    'external_reference' => (string) $reward->invite->qualification_business_key,
                ],
            );
        } catch (Throwable $exception) {
            return ReferralFulfillmentResult::failed('Affiliate award failed: ' . $exception->getMessage());
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

    public function reverse(ReferralReward $reward, string $reason): ReferralFulfillmentResult
    {
        $conversionClass = 'AIArmada\\Affiliates\\Models\\AffiliateConversion';
        $actionClass = 'AIArmada\\Affiliates\\Actions\\Conversions\\ReverseAffiliateConversion';
        $conversionId = $reward->external_reference;

        if (! is_string($conversionId) || $conversionId === '' || ! class_exists($conversionClass) || ! class_exists($actionClass)) {
            return ReferralFulfillmentResult::failed('Affiliate reversal requires the stored conversion id and affiliates package.');
        }

        /** @var Model $conversionModel */
        $conversionModel = app($conversionClass);
        $conversion = $conversionModel->newQuery()->whereKey($conversionId)->first();

        if (! $conversion instanceof Model) {
            return ReferralFulfillmentResult::failed('The affiliate conversion linked to this referral could not be found.');
        }

        $inviteId = (string) $reward->invite_id;
        $side = $reward->side instanceof ReferralRewardSide ? $reward->side->value : (string) $reward->side;
        $expectedKey = hash('sha256', 'referrals:award:' . $inviteId . ':' . $side);

        if ($conversion->getAttribute('idempotency_key') !== $expectedKey) {
            return ReferralFulfillmentResult::failed('The affiliate conversion idempotency key does not match this referral reward.');
        }

        try {
            $action = app($actionClass);
            $action->handle($conversion, $reason);
        } catch (Throwable $exception) {
            return ReferralFulfillmentResult::failed('Affiliate reversal failed: ' . $exception->getMessage());
        }

        return ReferralFulfillmentResult::fulfilled($conversionId);
    }
}

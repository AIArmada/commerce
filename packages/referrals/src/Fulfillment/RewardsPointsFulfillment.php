<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Fulfillment;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Models\ReferralReward;
use Throwable;

final class RewardsPointsFulfillment implements ReferralRewardFulfillment
{
    public function fulfill(ReferralReward $reward): ReferralFulfillmentResult
    {
        $readerClass = 'AIArmada\\Rewards\\Contracts\\RewardSourceFactReader';
        $factClass = 'AIArmada\\Rewards\\Support\\RewardSourceFact';
        $jobClass = 'AIArmada\\Rewards\\Jobs\\EvaluateRewardEntitlementJob';
        $awardClass = 'AIArmada\\Rewards\\Models\\RewardAward';

        if (! $this->symbolsExist([$readerClass, $factClass, $jobClass, $awardClass])) {
            return ReferralFulfillmentResult::failed('The rewards entitlement job API is unavailable.');
        }

        $businessKey = 'referrals:qualified:' . $reward->invite_id . ':' . $reward->side->value;

        try {
            $reader = app($readerClass);
            $fact = $reader->read($businessKey);
        } catch (Throwable $exception) {
            return ReferralFulfillmentResult::failed('The rewards source fact could not be read: ' . $exception->getMessage());
        }

        if (! is_object($fact) || ! is_a($fact, $factClass)) {
            return ReferralFulfillmentResult::failed('The host RewardSourceFactReader does not recognize this referral business key.');
        }

        $recipientTypes = config('rewards.recipient_types', []);
        $expectedRecipientId = (string) $reward->beneficiary_id;
        $sourceRecipientId = $reward->side === ReferralRewardSide::Referrer
            ? $fact->sharerId
            : $fact->actorId;

        if ($fact->sourceBusinessKey !== $businessKey
            || $fact->outcome !== 'referral_qualified'
            || ! $fact->isAttributed
            || ! $fact->isClaimedIdentity
            || ! $fact->isValid
            || $fact->isCancelled
            || ! is_array($recipientTypes)
            || ! is_string($reward->beneficiary_type)
            || ($recipientTypes[0] ?? null) !== $reward->beneficiary_type
            || (string) $sourceRecipientId !== $expectedRecipientId) {
            return ReferralFulfillmentResult::failed('The host rewards source fact does not match the frozen referral beneficiary and outcome.');
        }

        try {
            $jobClass::dispatchSync($businessKey);
        } catch (Throwable $exception) {
            return ReferralFulfillmentResult::failed('Rewards entitlement dispatch failed: ' . $exception->getMessage());
        }

        try {
            $award = $awardClass::query()
                ->where('source_business_key', $businessKey)
                ->where('recipient_type', $reward->beneficiary_type)
                ->where('recipient_id', $expectedRecipientId)
                ->where('component', 'points')
                ->whereIn('status', ['held', 'pending_review', 'approved', 'fulfilled'])
                ->first();
        } catch (Throwable $exception) {
            return ReferralFulfillmentResult::failed('The rewards award could not be verified: ' . $exception->getMessage());
        }

        if (! is_object($award)) {
            return ReferralFulfillmentResult::failed('Rewards evaluation completed without creating a non-refused points award for this referral.');
        }

        $spec = is_array($reward->spec) ? $reward->spec : [];
        $amountSnapshot = $award->amount_snapshot ?? null;
        $awardedPoints = is_array($amountSnapshot) ? ($amountSnapshot['points'] ?? null) : null;

        if (array_key_exists('points', $spec)
            && is_int($spec['points'])
            && is_int($awardedPoints)
            && $awardedPoints !== $spec['points']) {
            return ReferralFulfillmentResult::failed('The rewards award points amount does not match the frozen referral points spec.');
        }

        return ReferralFulfillmentResult::fulfilled($businessKey);
    }

    public function reverse(ReferralReward $reward, string $reason): ReferralFulfillmentResult
    {
        $businessKey = 'referrals:qualified:' . $reward->invite_id . ':' . $reward->side->value;
        $metadata = is_array($reward->metadata) ? $reward->metadata : [];
        $metadata['rewards_reversal'] = 'pending_host_corrections_sweep';
        $metadata['rewards_reversal_business_key'] = $businessKey;
        $reward->metadata = $metadata;
        $reward->save();

        return ReferralFulfillmentResult::fulfilled($reward->external_reference ?? $businessKey);
    }

    /**
     * @param  list<string>  $symbolNames
     */
    private function symbolsExist(array $symbolNames): bool
    {
        foreach ($symbolNames as $symbolName) {
            if (! class_exists($symbolName) && ! interface_exists($symbolName)) {
                return false;
            }
        }

        return true;
    }
}

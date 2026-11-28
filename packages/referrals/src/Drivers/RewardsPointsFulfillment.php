<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Drivers;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Data\ReferralRewardContext;
use AIArmada\Rewards\Features\Entitlements\Api\RewardPointsEntitlementApi;
use Throwable;

final class RewardsPointsFulfillment implements ReferralRewardFulfillment
{
    public function fulfill(ReferralRewardContext $reward): ReferralFulfillmentResult
    {
        $readerClass = 'AIArmada\\Rewards\\Contracts\\RewardSourceFactReader';
        $factClass = 'AIArmada\\Rewards\\Data\\RewardSourceFact';
        if (! $this->symbolsExist([$readerClass, $factClass, RewardPointsEntitlementApi::class]) || ! app()->bound(RewardPointsEntitlementApi::class)) {
            return ReferralFulfillmentResult::failed('The rewards entitlement API is unavailable.');
        }

        $entitlements = app(RewardPointsEntitlementApi::class);

        if ($reward->connectionName === '' || $entitlements->connectionName() !== $reward->connectionName) {
            return ReferralFulfillmentResult::failed('Rewards points referral fulfilment requires a shared database connection.');
        }

        $businessKey = 'referrals:qualified:' . $reward->inviteId . ':' . $reward->side;

        try {
            $reader = app($readerClass);
            $fact = $reader->read($businessKey);
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('The rewards source fact could not be read.');
        }

        if (! is_object($fact) || ! is_a($fact, $factClass)) {
            return ReferralFulfillmentResult::failed('The host RewardSourceFactReader does not recognize this referral business key.');
        }

        $recipientTypes = config('rewards.recipient_types', []);
        $expectedRecipientId = (string) $reward->beneficiaryId;
        $sourceRecipientId = $reward->side === 'referrer'
            ? $fact->sharerId
            : $fact->actorId;

        if ($fact->sourceBusinessKey !== $businessKey
            || $fact->outcome !== 'referral_qualified'
            || ! $fact->isAttributed
            || ! $fact->isClaimedIdentity
            || ! $fact->isValid
            || $fact->isCancelled
            || ! is_array($recipientTypes)
            || ! is_string($reward->beneficiaryType)
            || ! in_array($reward->beneficiaryType, $recipientTypes, true)
            || (string) $sourceRecipientId !== $expectedRecipientId) {
            return ReferralFulfillmentResult::failed('The host rewards source fact does not match the frozen referral beneficiary and outcome.');
        }

        // Side-scoped evaluation: the referrer is the attributed sharer, the
        // referee the acting participant. Dispatching with only that (role,
        // recipient, family) tuple evaluates one candidate instead of the
        // whole fact's every-side fan-out, so each side's fulfilment creates
        // exactly one award instead of leaving orphaned cross-side rows.
        $roleValue = $reward->side === 'referrer' ? 'sharer' : 'participant';
        $spec = $reward->spec;
        $familyKey = $spec['family_key'] ?? null;

        if ($familyKey !== null && ! is_string($familyKey)) {
            return ReferralFulfillmentResult::failed('The frozen referral family_key must be a string.');
        }

        try {
            $entitlements->evaluatePoints(
                $businessKey,
                (string) $reward->beneficiaryType,
                $expectedRecipientId,
                $roleValue,
                $familyKey,
                $reward->points,
            );
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('The rewards points rail could not settle this side.');
        }

        return ReferralFulfillmentResult::fulfilled($businessKey);
    }

    public function reverse(ReferralRewardContext $reward, string $reason): ReferralFulfillmentResult
    {
        $businessKey = 'referrals:qualified:' . $reward->inviteId . ':' . $reward->side;

        if (! interface_exists(RewardPointsEntitlementApi::class) || ! app()->bound(RewardPointsEntitlementApi::class)) {
            return ReferralFulfillmentResult::failed('The rewards corrections API is unavailable.');
        }

        $entitlements = app(RewardPointsEntitlementApi::class);

        if ($reward->connectionName === '' || $entitlements->connectionName() !== $reward->connectionName) {
            return ReferralFulfillmentResult::failed('Rewards points referral reversal requires a shared database connection.');
        }

        $roleValue = $reward->side === 'referrer' ? 'sharer' : 'participant';

        try {
            $entitlements->reversePoints(
                $businessKey,
                (string) $reward->beneficiaryType,
                (string) $reward->beneficiaryId,
                $roleValue,
                'referral-reversal',
                [
                    'referral_reward_id' => $reward->id,
                    'invite_id' => $reward->inviteId,
                    'side' => $reward->side,
                    'reason' => $reason,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            return ReferralFulfillmentResult::failed('The rewards corrections sweep could not be driven.');
        }

        return $this->stampVerifiedReversal($reward, $businessKey);
    }

    private function stampVerifiedReversal(ReferralRewardContext $reward, string $businessKey): ReferralFulfillmentResult
    {
        return ReferralFulfillmentResult::fulfilled($reward->externalReference ?? $businessKey, [
            'rewards_reversal' => 'verified_corrections_sweep',
            'rewards_reversal_business_key' => $businessKey,
        ]);
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

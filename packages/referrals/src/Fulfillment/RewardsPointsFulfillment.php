<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Fulfillment;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Models\ReferralReward;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RewardsPointsFulfillment implements ReferralRewardFulfillment
{
    public function fulfill(ReferralReward $reward): ReferralFulfillmentResult
    {
        $readerClass = 'AIArmada\\Rewards\\Contracts\\RewardSourceFactReader';
        $factClass = 'AIArmada\\Rewards\\Support\\RewardSourceFact';
        $jobClass = 'AIArmada\\Rewards\\Jobs\\EvaluateRewardEntitlementJob';
        $awardClass = 'AIArmada\\Rewards\\Models\\RewardAward';
        /** @var class-string $roleClass */
        $roleClass = 'AIArmada\\Rewards\\Enums\\RewardBeneficiaryRole';

        if (! $this->symbolsExist([$readerClass, $factClass, $jobClass, $awardClass, $roleClass])) {
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
            || ! in_array($reward->beneficiary_type, $recipientTypes, true)
            || (string) $sourceRecipientId !== $expectedRecipientId) {
            return ReferralFulfillmentResult::failed('The host rewards source fact does not match the frozen referral beneficiary and outcome.');
        }

        // Side-scoped evaluation: the referrer is the attributed sharer, the
        // referee the acting participant. Dispatching with only that (role,
        // recipient, family) tuple evaluates one candidate instead of the
        // whole fact's every-side fan-out, so each side's fulfilment creates
        // exactly one award instead of leaving orphaned cross-side rows.
        $roleValue = $reward->side === ReferralRewardSide::Referrer ? 'sharer' : 'participant';
        $onlyRole = $roleClass::from($roleValue);
        $spec = is_array($reward->spec) ? $reward->spec : [];
        $familyKey = $spec['family_key'] ?? null;

        if ($familyKey !== null && ! is_string($familyKey)) {
            return ReferralFulfillmentResult::failed('The frozen referral family_key must be a string.');
        }

        // Dispatch and probe share one (nested) transaction: any post-dispatch
        // failure — no award, an ambiguous award, or a mis-sized award —
        // throws out of this unit, so no RewardAward rows persist for the
        // side key. The outer `FulfillReferralReward` unit then marks the
        // reward Failed, and a retry after host rule alignment re-evaluates
        // from a clean slate.
        try {
            DB::transaction(function () use ($jobClass, $businessKey, $expectedRecipientId, $onlyRole, $reward, $familyKey, $roleValue, $awardClass): void {
                $jobClass::dispatchSync(
                    $businessKey,
                    onlyRecipientId: $expectedRecipientId,
                    onlyRole: $onlyRole,
                    onlyRecipientType: (string) $reward->beneficiary_type,
                    onlyFamilyKey: $familyKey,
                );

                $awards = $awardClass::query()
                    ->where('source_business_key', $businessKey)
                    ->where('recipient_type', (string) $reward->beneficiary_type)
                    ->where('recipient_id', $expectedRecipientId)
                    ->where('component', 'points')
                    ->where('beneficiary_role', $roleValue)
                    ->when(
                        $familyKey !== null,
                        static fn (Builder $query): Builder => $query->where('family_key', $familyKey),
                    )
                    ->whereIn('status', ['held', 'pending_review', 'approved', 'fulfilled'])
                    ->orderBy('id')
                    ->get();

                // Exactly one live award: unordered first() acceptance hid
                // orphaned cross-side and cross-family rows.
                if ($awards->count() !== 1) {
                    throw new RuntimeException($awards->isEmpty()
                        ? 'Rewards evaluation completed without creating a live points award for this referral side.'
                        : sprintf('Rewards evaluation found %d live points awards for this referral side; exactly one is required.', $awards->count()));
                }

                $award = $awards->first();
                $promisedPoints = $reward->points;
                $awardedPoints = is_array($award->amount_snapshot) ? ($award->amount_snapshot['points'] ?? null) : null;

                if (! is_int($promisedPoints) || ! is_int($awardedPoints) || $awardedPoints !== $promisedPoints) {
                    throw new RuntimeException('The rewards award points amount does not match the frozen referral points spec.');
                }
            });
        } catch (Throwable $exception) {
            return ReferralFulfillmentResult::failed('The rewards points rail could not settle this side: ' . $exception->getMessage());
        }

        return ReferralFulfillmentResult::fulfilled($businessKey);
    }

    public function reverse(ReferralReward $reward, string $reason): ReferralFulfillmentResult
    {
        $businessKey = 'referrals:qualified:' . $reward->invite_id . ':' . $reward->side->value;

        $awardClass = 'AIArmada\\Rewards\\Models\\RewardAward';
        $correctionsClass = 'AIArmada\\Rewards\\Actions\\ApplyRewardSourceCorrectionsAction';

        if (! $this->symbolsExist([$awardClass, $correctionsClass])) {
            return ReferralFulfillmentResult::failed('The rewards corrections API is unavailable.');
        }

        $roleValue = $reward->side === ReferralRewardSide::Referrer ? 'sharer' : 'participant';

        $liveAwardIds = $this->liveSideAwardIds($reward, $roleValue, $businessKey);

        if ($liveAwardIds === []) {
            // Nothing live to claw back. The host corrections sweep
            // converges (settled work skips), so the metadata records the
            // verified sweep instead of a pending host contract.
            return $this->stampVerifiedReversal($reward, $businessKey);
        }

        // Live awards exist: drive the corrections sweep with this side's
        // business key as an explicit tainted key. Held and pending-review
        // awards reject, approved awards reverse, and fulfilled awards route
        // through the recovery-aware connector. The sweep runs each award
        // under its own program owner, so a referral owner that differs from
        // the rewards program owner still settles. A blocked reversal (an
        // unattested active reservation, a missing symbol, any throw)
        // surfaces as `failed()`: `ReverseReferralReward` then throws and
        // rolls its unit back, so the referral stays Fulfilled and retryable
        // instead of terminal Reversed with spendable points.
        try {
            $report = $correctionsClass::run('system', null, 'system', null, 200, [
                [
                    'business_key' => $businessKey,
                    'reason' => 'referral-reversal',
                    'evidence' => [
                        'referral_reward_id' => (string) $reward->getKey(),
                        'invite_id' => (string) $reward->invite_id,
                        'side' => $reward->side->value,
                        'reason' => $reason,
                    ],
                ],
            ]);
        } catch (Throwable $exception) {
            return ReferralFulfillmentResult::failed('The rewards corrections sweep could not be driven: ' . $exception->getMessage());
        }

        foreach ($liveAwardIds as $awardId) {
            if (array_key_exists($awardId, $report['errors'])) {
                return ReferralFulfillmentResult::failed('The rewards corrections sweep reported an error for this referral side award.');
            }
        }

        if ($this->liveSideAwards($reward, $roleValue, $businessKey)) {
            return ReferralFulfillmentResult::failed('The rewards corrections sweep left a live points award for this referral side.');
        }

        return $this->stampVerifiedReversal($reward, $businessKey);
    }

    /**
     * A live (held, pending-review, approved, or fulfilled) points award for
     * the frozen side's (business key, recipient, role) tuple.
     *
     * @phpstan-impure
     */
    private function liveSideAwards(ReferralReward $reward, string $roleValue, string $businessKey): bool
    {
        return $this->liveSideAwardIds($reward, $roleValue, $businessKey) !== [];
    }

    /**
     * @return list<string>
     */
    private function liveSideAwardIds(ReferralReward $reward, string $roleValue, string $businessKey): array
    {
        /** @var class-string $awardClass */
        $awardClass = 'AIArmada\\Rewards\\Models\\RewardAward';

        /** @var list<string> $ids */
        $ids = $awardClass::query()
            ->where('source_business_key', $businessKey)
            ->where('recipient_type', (string) $reward->beneficiary_type)
            ->where('recipient_id', (string) $reward->beneficiary_id)
            ->where('component', 'points')
            ->where('beneficiary_role', $roleValue)
            ->whereIn('status', ['held', 'pending_review', 'approved', 'fulfilled'])
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        return $ids;
    }

    private function stampVerifiedReversal(ReferralReward $reward, string $businessKey): ReferralFulfillmentResult
    {
        $metadata = is_array($reward->metadata) ? $reward->metadata : [];
        $metadata['rewards_reversal'] = 'verified_corrections_sweep';
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

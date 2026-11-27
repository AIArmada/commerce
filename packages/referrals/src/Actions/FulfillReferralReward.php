<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Data\ReferralFulfillmentResult;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Events\ReferralRewardFulfilled;
use AIArmada\Referrals\Exceptions\ReferralFulfillmentException;
use AIArmada\Referrals\Models\ReferralReward;
use AIArmada\Referrals\Persistence\ReferralRewardContextMapper;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class FulfillReferralReward
{
    use AsAction;

    public function handle(ReferralReward | string $reward, ?string $manualReference = null): ReferralReward
    {
        $rewardId = $reward instanceof ReferralReward ? (string) $reward->getKey() : $reward;
        $failure = null;

        /** @var array{reward: ReferralReward, fulfilled: bool} $outcome */
        $outcome = DB::transaction(function () use ($rewardId, $manualReference, &$failure): array {
            $guard = app(ReferralProgramGuard::class);
            $scopedReward = $guard->reward($rewardId);
            $invite = $guard->invite((string) $scopedReward->invite_id, true);
            $lockedReward = ReferralReward::query()
                ->where('program_id', $invite->program_id)
                ->where('invite_id', $invite->getKey())
                ->whereKey($rewardId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($manualReference !== null && $lockedReward->driver !== 'manual') {
                throw new LogicException('Only manual referral rewards accept a fulfilment reference.');
            }

            if ($lockedReward->status === ReferralRewardStatus::Fulfilled) {
                if ($manualReference !== null && mb_trim($manualReference) !== $lockedReward->external_reference) {
                    throw new LogicException('The manual fulfilment reference cannot be replaced.');
                }

                return ['reward' => $lockedReward, 'fulfilled' => false];
            }

            if ($lockedReward->status === ReferralRewardStatus::Reversed) {
                throw new LogicException('A reversed referral reward cannot be fulfilled again.');
            }

            $result = null;

            try {
                $lockedReward->getConnection()->transaction(function () use ($lockedReward, $invite, $manualReference, &$result): void {
                    $result = app(ReferralRewardFulfillment::class)->fulfill(ReferralRewardContextMapper::map($lockedReward, $invite->qualification_business_key, $manualReference));

                    if (! $result->success) {
                        throw new ReferralFulfillmentException($result->reason ?? 'The fulfilment adapter refused this reward.');
                    }
                });
            } catch (Throwable $exception) {
                if ($result instanceof ReferralFulfillmentResult && ! $result->success) {
                    $this->markFailed($lockedReward, $result->reason ?? 'The fulfilment adapter refused this reward.');

                    return ['reward' => $lockedReward, 'fulfilled' => false];
                }

                $failure = $exception instanceof ReferralFulfillmentException
                    ? $exception
                    : new ReferralFulfillmentException('Referral reward fulfilment failed.', previous: $exception);
                report($exception);
                $this->markFailed($lockedReward, 'The fulfilment adapter encountered an error.');

                return ['reward' => $lockedReward, 'fulfilled' => false];
            }

            if (! $result instanceof ReferralFulfillmentResult) {
                throw new LogicException('The fulfilment adapter returned no result.');
            }

            $metadata = array_replace($lockedReward->metadata ?? [], $result->metadata);
            unset($metadata['fulfillment_failure']);
            $lockedReward->metadata = $metadata;
            $lockedReward->status = ReferralRewardStatus::Fulfilled;
            $lockedReward->fulfilled_at ??= CarbonImmutable::now();

            if ($lockedReward->external_reference === null) {
                $lockedReward->external_reference = $result->externalReference;
            } elseif ($result->externalReference !== null && $lockedReward->external_reference !== $result->externalReference) {
                throw new ReferralFulfillmentException('The fulfilment adapter returned a different external reference on replay.');
            }

            $lockedReward->save();

            $pendingRewards = ReferralReward::query()
                ->where('invite_id', $invite->getKey())
                ->where('status', '!=', ReferralRewardStatus::Fulfilled->value)
                ->exists();

            if (! $pendingRewards && $invite->status === ReferralInviteStatus::Qualified) {
                $invite->status = ReferralInviteStatus::Rewarded;
                $invite->rewarded_at ??= CarbonImmutable::now();
                $invite->save();
            }

            return ['reward' => $lockedReward, 'fulfilled' => true];
        });

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        if ($outcome['fulfilled']) {
            ReferralRewardFulfilled::dispatch($outcome['reward']->fresh());
        }

        return $outcome['reward']->fresh();
    }

    private function markFailed(ReferralReward $reward, string $reason): void
    {
        $metadata = is_array($reward->metadata) ? $reward->metadata : [];
        $metadata['fulfillment_failure'] = $reason;
        $reward->metadata = $metadata;
        $reward->status = ReferralRewardStatus::Failed;
        $reward->save();
    }
}

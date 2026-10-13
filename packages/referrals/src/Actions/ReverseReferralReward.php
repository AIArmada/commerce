<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Events\ReferralRewardReversed;
use AIArmada\Referrals\Exceptions\ReferralFulfillmentException;
use AIArmada\Referrals\Models\ReferralReward;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class ReverseReferralReward
{
    use AsAction;

    public function handle(ReferralReward | string $reward, string $reason): ReferralReward
    {
        $reason = mb_trim($reason);

        if ($reason === '') {
            throw new LogicException('A referral reward reversal requires a reason.');
        }

        $rewardId = $reward instanceof ReferralReward ? (string) $reward->getKey() : $reward;
        $reversed = DB::transaction(function () use ($rewardId, $reason): array {
            $guard = app(ReferralProgramGuard::class);
            $scopedReward = $guard->reward($rewardId);
            $invite = $guard->invite((string) $scopedReward->invite_id, true);
            $lockedReward = ReferralReward::query()
                ->where('program_id', $invite->program_id)
                ->where('invite_id', $invite->getKey())
                ->whereKey($rewardId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReward->status === ReferralRewardStatus::Reversed) {
                return ['reward' => $lockedReward, 'reversed' => false];
            }

            if ($lockedReward->status !== ReferralRewardStatus::Fulfilled) {
                throw new LogicException('Only fulfilled referral rewards can be reversed.');
            }

            $result = app(ReferralRewardFulfillment::class)->reverse($lockedReward, $reason);

            if (! $result->success) {
                throw new ReferralFulfillmentException($result->reason ?? 'The fulfilment adapter refused the reversal.');
            }

            if ($lockedReward->external_reference !== null
                && $result->externalReference !== null
                && $lockedReward->external_reference !== $result->externalReference) {
                throw new ReferralFulfillmentException('The reversal adapter returned a different external reference.');
            }

            $metadata = is_array($lockedReward->metadata) ? $lockedReward->metadata : [];
            $metadata['reversal_reason'] = $reason;
            $lockedReward->metadata = $metadata;
            $lockedReward->status = ReferralRewardStatus::Reversed;
            $lockedReward->reversed_at = CarbonImmutable::now();
            $lockedReward->save();

            if ($invite->status === ReferralInviteStatus::Rewarded) {
                $invite->status = ReferralInviteStatus::Qualified;
                $invite->rewarded_at = null;
                $invite->save();
            }

            return ['reward' => $lockedReward, 'reversed' => true];
        });

        if ($reversed['reversed']) {
            ReferralRewardReversed::dispatch($reversed['reward']->fresh());
        }

        return $reversed['reward']->fresh();
    }
}

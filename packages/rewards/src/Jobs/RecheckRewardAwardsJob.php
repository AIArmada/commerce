<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Jobs;

use AIArmada\Rewards\Actions\InvalidateRewardMilestoneContributionAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Contracts\RewardCorrectionKeySource;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardSourceCorrectionStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Support\RewardSourceFact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Rechecks awards after a source correction lands.
 *
 * The host dispatches this job with the business key when its own
 * correction store records a correction; core re-reads the current fact
 * through the reader boundary and acts only when the fact is no longer
 * current. When the key has no readable fact (a correction recorded
 * without full attribution rows), the bound correction-key source is
 * consulted instead so the tainted key still settles. Correction wins
 * races with queued evaluation: unfulfilled
 * awards on the corrected source move to rejected (held/pending_review)
 * or reversed (approved) with the correction as evidence. Fulfilled
 * awards are NEVER auto-reversed here — post-fulfillment value needs the
 * authorized recovery/settlement workflow, not a silent reversal.
 * Milestone contributions on the source are invalidated so cycles
 * re-evaluate.
 */
final class RecheckRewardAwardsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $businessKey,
    ) {}

    public function uniqueId(): string
    {
        return 'reward-recheck:' . $this->businessKey;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        RewardSourceFactReader $reader,
        TransitionRewardAwardAction $transition,
        InvalidateRewardMilestoneContributionAction $invalidate,
        ?RewardCorrectionKeySource $keys = null,
    ): void {
        $fact = $reader->read($this->businessKey);

        if ($fact instanceof RewardSourceFact) {
            if ($fact->correctionStatus === RewardSourceCorrectionStatus::Current) {
                return;
            }

            $evidence = [
                'correction_id' => $fact->correctionId,
                'correction_status' => $fact->correctionStatus->value,
                'business_key' => $this->businessKey,
                'source_snapshot' => $fact->snapshot(),
            ];
        } else {
            $cause = $keys?->causeFor($this->businessKey);

            if ($cause === null) {
                return;
            }

            $evidence = [
                'correction_reason' => $cause['reason'],
                'business_key' => $this->businessKey,
                'source_correction' => $cause['evidence'],
            ];
        }

        $awards = RewardAward::query()
            ->where('source_business_key', $this->businessKey)
            ->whereIn('status', [
                RewardAwardStatus::Held->value,
                RewardAwardStatus::PendingReview->value,
                RewardAwardStatus::Approved->value,
            ])
            ->orderBy('id')
            ->get();

        foreach ($awards as $award) {
            /** @var RewardAward $award */
            $to = $award->status === RewardAwardStatus::Approved
                ? RewardAwardStatus::Reversed
                : RewardAwardStatus::Rejected;

            try {
                $transition->handle(
                    $award,
                    $to,
                    'system',
                    null,
                    'system',
                    'source_withdrawn',
                    $evidence,
                );
            } catch (InvalidArgumentException $exception) {
                // A concurrent operator/system transition already moved the
                // award out of the expected state; anything else is a real
                // failure and must retry.
                if (str_starts_with($exception->getMessage(), 'Invalid award transition')) {
                    continue;
                }

                throw $exception;
            }
        }

        $contributions = RewardMilestoneContribution::query()
            ->where('source_business_key', $this->businessKey)
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get();

        foreach ($contributions as $contribution) {
            // The action is idempotent on revoked_at under the cycle lock.
            $invalidate->handle(
                $contribution,
                'system',
                null,
                'system',
                'source_withdrawn',
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\Rewards\Contracts\RewardCorrectionKeySource;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardSourceCorrectionStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Support\RewardProgramGuard;
use AIArmada\Rewards\Support\RewardSourceFact;
use InvalidArgumentException;

final readonly class RecheckRewardSourceAction
{
    public function __construct(
        private string $businessKey,
    ) {}

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
            ->lazyById();

        foreach ($awards as $award) {
            /** @var RewardAward $award */
            $to = $award->status === RewardAwardStatus::Approved
                ? RewardAwardStatus::Reversed
                : RewardAwardStatus::Rejected;

            try {
                RewardProgramGuard::withProgramOwner((string) $award->program_id, static function () use ($transition, $award, $to, $evidence): void {
                    $transition->handle(
                        $award,
                        $to,
                        'system',
                        null,
                        'system',
                        'source_withdrawn',
                        $evidence,
                    );
                });
            } catch (InvalidArgumentException $exception) {
                if (str_starts_with($exception->getMessage(), 'Invalid award transition')) {
                    continue;
                }

                throw $exception;
            }
        }

        $contributions = RewardMilestoneContribution::query()
            ->where('source_business_key', $this->businessKey)
            ->whereNull('revoked_at')
            ->with('cycle')
            ->lazyById();

        foreach ($contributions as $contribution) {
            RewardProgramGuard::withProgramOwner(
                (string) ($contribution->cycle?->program_id ?? ''),
                static fn () => $invalidate->handle(
                    $contribution,
                    'system',
                    null,
                    'system',
                    'source_withdrawn',
                ),
            );
        }
    }
}

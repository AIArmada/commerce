<?php

declare(strict_types=1);

namespace AIArmada\Rewards\EntryPoints\Jobs;

use AIArmada\Rewards\Features\Entitlements\Actions\RecheckRewardSourceAction;
use AIArmada\Rewards\Features\Entitlements\Actions\InvalidateRewardMilestoneContributionAction;
use AIArmada\Rewards\Features\Entitlements\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Contracts\RewardCorrectionKeySource;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        (new RecheckRewardSourceAction($this->businessKey))->handle($reader, $transition, $invalidate, $keys);
    }
}

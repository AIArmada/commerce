<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Contracts\RewardGratitudePlanner;
use Carbon\CarbonImmutable;

/**
 * No-op gratitude planner: core commits achievements without host
 * follow-ups. Hosts bind an implementation for digest planning.
 */
final class NullRewardGratitudePlanner implements RewardGratitudePlanner
{
    public function planFor(
        string $recipientType,
        string $recipientId,
        array $channels,
        CarbonImmutable $at,
    ): void {
        //
    }

    public function cancelPendingFor(
        string $recipientType,
        string $recipientId,
        string $reason,
        CarbonImmutable $at,
    ): void {
        //
    }
}

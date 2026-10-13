<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use Carbon\CarbonImmutable;

/**
 * Gratitude-notification hook boundary for reward achievements.
 *
 * Core commits the achievement first, then notifies the bound planner in
 * the same atomic unit (e.g. hosts plan delayed recipient digests for
 * award, badge, and milestone channels). Planning is preference-gated by
 * the host: opted-out recipients yield no slot, never a failed action —
 * core catches refusal-style exceptions so the achievement stands. The
 * default binding is a no-op.
 */
interface RewardGratitudePlanner
{
    /**
     * @param  list<string>  $channels  achievement channels (e.g. awards, badges, milestones).
     */
    public function planFor(
        string $recipientType,
        string $recipientId,
        array $channels,
        CarbonImmutable $at,
    ): void;

    public function cancelPendingFor(
        string $recipientType,
        string $recipientId,
        string $reason,
        CarbonImmutable $at,
    ): void;
}

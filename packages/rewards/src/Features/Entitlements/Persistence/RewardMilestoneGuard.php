<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Persistence;

use AIArmada\Rewards\Features\Entitlements\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Support\RewardProgramGuard;
use InvalidArgumentException;

final class RewardMilestoneGuard
{
    /**
     * Resolve the contribution and prove its cycle's program is in the
     * current owner scope. A cycle without a program throws — a
     * program-less contribution must fail closed, never resolve against
     * whatever ambient scope happens to exist.
     */
    public static function findOrFail(RewardMilestoneContribution | string $contribution): RewardMilestoneContribution
    {
        $id = $contribution instanceof RewardMilestoneContribution ? (string) $contribution->getKey() : $contribution;

        $resolved = RewardMilestoneContribution::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardMilestoneContribution) {
            throw new InvalidArgumentException('Unknown reward milestone contribution.');
        }

        $programId = $resolved->cycle?->program_id;

        if ($programId === null) {
            throw new InvalidArgumentException('Milestone contribution references no program.');
        }

        RewardProgramGuard::findProgramOrFail((string) $programId);

        return $resolved;
    }
}

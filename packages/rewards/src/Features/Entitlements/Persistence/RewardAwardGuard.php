<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Persistence;

use AIArmada\Rewards\Features\Entitlements\Models\RewardAppeal;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;
use InvalidArgumentException;

final class RewardAwardGuard
{
    /**
     * Resolve the award and the program it belongs to, proving both the
     * award exists and its program is in the current owner scope.
     */
    public static function findAwardOrFail(RewardAward | string $award): RewardAward
    {
        $id = $award instanceof RewardAward ? (string) $award->getKey() : $award;

        $resolved = RewardAward::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardAward) {
            throw new InvalidArgumentException('Unknown reward award.');
        }

        app(RewardProgramLockApi::class)->assertProgramInScope((string) $resolved->program_id);

        return $resolved;
    }

    /**
     * Scope-check an award attach reference only when the row exists.
     * Dangling references stay allowed for append-only annotations.
     */
    public static function assertAwardInScopeWhenExists(string $awardId): void
    {
        if (RewardAward::query()->whereKey($awardId)->exists()) {
            self::findAwardOrFail($awardId);
        }
    }

    /**
     * Scope-check an appeal attach reference only when the row exists.
     * Dangling references stay allowed for append-only annotations.
     */
    public static function assertAppealInScopeWhenExists(string $appealId): void
    {
        if (RewardAppeal::query()->whereKey($appealId)->exists()) {
            self::findAppealOrFail($appealId);
        }
    }

    /**
     * Resolve the appeal and prove its award's program is in the current
     * owner scope.
     */
    public static function findAppealOrFail(RewardAppeal | string $appeal): RewardAppeal
    {
        $id = $appeal instanceof RewardAppeal ? (string) $appeal->getKey() : $appeal;

        $resolved = RewardAppeal::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardAppeal) {
            throw new InvalidArgumentException('Unknown reward appeal.');
        }

        self::findAwardOrFail((string) $resolved->award_id);

        return $resolved;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Persistence;

use AIArmada\Rewards\Features\Entitlements\Models\RewardRecovery;
use InvalidArgumentException;

final class RewardRecoveryGuard
{
    /**
     * Resolve the recovery and prove its award's program is in the
     * current owner scope.
     */
    public static function findOrFail(RewardRecovery | string $recovery): RewardRecovery
    {
        $id = $recovery instanceof RewardRecovery ? (string) $recovery->getKey() : $recovery;

        $resolved = RewardRecovery::query()->whereKey($id)->first();

        if (! $resolved instanceof RewardRecovery) {
            throw new InvalidArgumentException('Unknown reward recovery.');
        }

        RewardAwardGuard::findAwardOrFail((string) $resolved->award_id);

        return $resolved;
    }
}

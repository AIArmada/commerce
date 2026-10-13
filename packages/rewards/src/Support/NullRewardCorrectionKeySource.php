<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Contracts\RewardCorrectionKeySource;

/**
 * Empty correction-key source: the sweep no-ops. Hosts bind an
 * implementation that reads their own correction/fraud stores.
 */
final class NullRewardCorrectionKeySource implements RewardCorrectionKeySource
{
    public function taintedKeys(int $limit): array
    {
        return [];
    }

    public function causeFor(string $businessKey): ?array
    {
        return null;
    }
}

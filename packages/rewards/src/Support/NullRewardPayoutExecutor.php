<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Contracts\RewardPayoutExecution;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use InvalidArgumentException;

/**
 * Fail-closed payout executor: every operation throws. Bound only when a
 * host explicitly wants payout execution disabled; the package default is
 * the manual operator executor.
 */
final class NullRewardPayoutExecutor implements RewardPayoutExecutor
{
    public function submit(RewardPayoutRequest $request): RewardPayoutExecution
    {
        throw new InvalidArgumentException('Payout execution is disabled: no RewardPayoutExecutor is bound.');
    }

    public function cancel(RewardPayoutRequest $request): bool
    {
        throw new InvalidArgumentException('Payout execution is disabled: no RewardPayoutExecutor is bound.');
    }

    public function status(RewardPayoutRequest $request): string
    {
        throw new InvalidArgumentException('Payout execution is disabled: no RewardPayoutExecutor is bound.');
    }

    public function settle(RewardPayoutRequest $request, string $externalStatus, array $context = []): void
    {
        throw new InvalidArgumentException('Payout execution is disabled: no RewardPayoutExecutor is bound.');
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Contracts\RewardPayoutExecution;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Models\RewardPayoutRequest;

/**
 * Manual operator payout executor: the package default.
 *
 * Submits mark the request submitted with a stable manual operation id and
 * await independent operator confirmation with transfer evidence — no
 * external call is ever made. Manual operations never reach a provider, so
 * cancellation always confirms and reconciliation reports the operation
 * as still submitted (operators confirm through the confirmation action,
 * never through reconcile).
 */
final class ManualRewardPayoutExecutor implements RewardPayoutExecutor
{
    public function submit(RewardPayoutRequest $request): RewardPayoutExecution
    {
        return new RewardPayoutExecution(
            status: 'pending',
            externalPayoutId: 'manual:' . (string) $request->operation_key,
            externalReferenceId: null,
            externalProvider: 'manual',
            externalReference: (string) $request->first_reference,
        );
    }

    public function cancel(RewardPayoutRequest $request): bool
    {
        // Manual operations never reach a provider: nothing holds them, so
        // operator cancellation always confirms.
        return true;
    }

    public function status(RewardPayoutRequest $request): string
    {
        return 'submitted';
    }

    public function settle(RewardPayoutRequest $request, string $externalStatus, array $context = []): void
    {
        // No provider-side ledger exists for manual operations; core owns
        // the full settlement. The frozen snapshot on the request stays
        // the operator's reconciliation authority.
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use AIArmada\Rewards\Models\RewardPayoutRequest;

/**
 * Cash payout execution boundary.
 *
 * The core payout actions own frozen terms, budget legs, idempotency keys,
 * and status mapping; this contract owns the provider side: submitting the
 * frozen operation to a rail, confirming provider-side cancellation, reading
 * provider status for reconciliation, and settling the provider-side ledger
 * once core has proven an outcome. Provider calls always run outside core
 * transactions, under the payout send mutex held by the calling action.
 *
 * The default binding is the manual operator executor; hosts with an
 * automated rail bind their own implementation.
 */
interface RewardPayoutExecutor
{
    /**
     * Submit the frozen request to the rail. Never recomputes terms: the
     * executor serves amount, fee, currency, and destination exactly as
     * frozen on the request.
     */
    public function submit(RewardPayoutRequest $request): RewardPayoutExecution;

    /**
     * Ask the provider to cancel a submitted operation. True ONLY when the
     * provider confirms the cancel; false, unsupported, or uncertain keeps
     * the request, its reservation, and its original operation intact.
     */
    public function cancel(RewardPayoutRequest $request): bool;

    /**
     * Read the provider-side status for reconciliation. Returns the raw
     * provider status string; core maps completed/failed families and
     * keeps anything else as-is with the original operation intact.
     */
    public function status(RewardPayoutRequest $request): string;

    /**
     * Settle the provider-side ledger after core has proven an outcome
     * (completed/cancelled/failed). Never invents an outcome: it records
     * the outcome core passes in.
     *
     * @param  array<string, mixed>  $context  outcome evidence (references, stamps).
     */
    public function settle(RewardPayoutRequest $request, string $externalStatus, array $context = []): void;
}

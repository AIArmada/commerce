<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Enums\RewardBudgetMovementKind;
use AIArmada\Rewards\Enums\RewardPayoutRequestStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use AIArmada\Rewards\Support\RewardBudgetLedger;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use AIArmada\Rewards\Support\RewardProgramGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Independent operator confirmation of a submitted payout.
 *
 * The confirmer must be a staff/admin user who is neither the recipient
 * nor the original requester, and must present the bank/provider transfer
 * evidence (reference + transfer date). Confirmation settles BOTH
 * ledgers through the bound executor (provider completed) plus the
 * budget spend; the budget invariant is asserted before the stamp.
 *
 * The per-request send mutex serializes confirmation against in-flight
 * submits, cancels, and reconciles for the same request.
 */
final readonly class ConfirmRewardPayoutAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $transferEvidence
     */
    public function handle(
        RewardPayoutRequest | string $request,
        array $transferEvidence,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        ?CarbonInterface $decidedAt = null,
    ): RewardPayoutRequest {
        RewardProgramGuard::findPayoutRequestOrFail($request);

        RewardLedgerEntries::assertActor($actorType, $actorId, $actorRole);

        if ($actorType !== 'user' || ! in_array($actorRole, ['staff', 'admin'], true)) {
            throw new InvalidArgumentException('Payout confirmation requires a staff/admin user identity.');
        }

        $bankReference = mb_trim((string) ($transferEvidence['bank_reference'] ?? ''));

        if ($bankReference === '' || mb_strlen($bankReference) > 191) {
            throw new InvalidArgumentException('Transfer evidence must carry a bank_reference of 1..191 characters.');
        }

        $requestId = $request instanceof RewardPayoutRequest ? (string) $request->getKey() : $request;
        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        $pre = RewardPayoutRequest::query()->whereKey($requestId)->first();

        if (! $pre instanceof RewardPayoutRequest) {
            throw new InvalidArgumentException('Unknown reward payout request.');
        }

        if ($pre->status !== RewardPayoutRequestStatus::Submitted) {
            throw new InvalidArgumentException('Only submitted payouts can be confirmed.');
        }

        try {
            return Cache::lock('rewards:payout:' . $requestId, 30)->block(10, function () use (
                $requestId,
                $transferEvidence,
                $bankReference,
                $actorId,
                $decided,
            ): RewardPayoutRequest {
                return DB::transaction(function () use (
                    $requestId,
                    $transferEvidence,
                    $bankReference,
                    $actorId,
                    $decided,
                ): RewardPayoutRequest {
                    $pre = RewardPayoutRequest::query()->whereKey($requestId)->firstOrFail();

                    RewardAward::query()->whereKey((string) $pre->award_id)->lockForUpdate()->firstOrFail();

                    $locked = RewardPayoutRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

                    if ($locked->status !== RewardPayoutRequestStatus::Submitted) {
                        throw new InvalidArgumentException('Only submitted payouts can be confirmed.');
                    }

                    $award = RewardAward::query()->whereKey((string) $locked->award_id)->firstOrFail();

                    if ($actorId === (string) $award->recipient_id) {
                        throw new InvalidArgumentException('Recipient-owned payout confirmation is prohibited.');
                    }

                    if ($actorId === (string) $locked->requested_by_id) {
                        throw new InvalidArgumentException('Payout confirmation requires a confirmer independent of the requester.');
                    }

                    if ($locked->external_payout_id === null) {
                        throw new InvalidArgumentException('Submitted payout carries no provider operation; refusing blind confirmation.');
                    }

                    $total = (int) $locked->amount_minor + ((string) $locked->fee_payer === 'program' ? (int) $locked->fee_minor : 0);
                    $programId = (string) $locked->program_id;
                    $currency = (string) $locked->currency;

                    $balances = RewardBudgetLedger::lockedBalances(
                        $programId,
                        $currency,
                        RewardBudgetLedger::lockFunding($programId, $currency)
                    );

                    if ($balances['reserved'] < $total) {
                        throw new InvalidArgumentException('Payout reservation is missing; refusing to confirm.');
                    }

                    // Provider completion first through the bound executor,
                    // then the budget spend. The executor records the
                    // outcome; it never invents one.
                    app(RewardPayoutExecutor::class)->settle(
                        $locked,
                        'completed',
                        ['reference' => $bankReference, 'status' => 'manual-confirmed']
                    );

                    RewardBudgetLedger::append(
                        RewardBudgetMovementKind::Spend,
                        "spend:{$locked->award_id}",
                        $programId,
                        $currency,
                        $total,
                        (string) $locked->award_id,
                        'user',
                        $actorId,
                        'staff',
                        $decided,
                    );

                    RewardBudgetLedger::append(
                        RewardBudgetMovementKind::Release,
                        "release:{$locked->award_id}:spent",
                        $programId,
                        $currency,
                        $total,
                        (string) $locked->award_id,
                        'user',
                        $actorId,
                        'staff',
                        $decided,
                    );

                    RewardBudgetLedger::assertNonNegativeLegs(
                        RewardBudgetLedger::lockedBalances(
                            $programId,
                            $currency,
                            RewardBudgetLedger::lockFunding($programId, $currency)
                        )
                    );

                    $evidence = $locked->evidence ?? [];
                    $evidence['transfer'] = $transferEvidence;

                    $locked->forceFill([
                        'status' => RewardPayoutRequestStatus::Confirmed,
                        'provider_reference' => $bankReference,
                        'evidence' => $evidence,
                        'confirmed_by' => $actorId,
                        'confirmed_at' => $decided,
                    ])->save();

                    return $locked->refresh();
                });
            });
        } catch (LockTimeoutException) {
            throw new InvalidArgumentException('Another payout operation for this request is in flight; retry the confirmation.');
        }
    }
}

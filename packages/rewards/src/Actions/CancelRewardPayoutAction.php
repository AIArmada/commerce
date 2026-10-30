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
use Throwable;

/**
 * Cancel a payout request.
 *
 * The durable provider operation is inspected before anything moves: a
 * request WITH a bound operation is never treated as never-submitted.
 * Operations the provider may hold cancel ONLY when the provider
 * confirms the cancel through the bound executor: cancel=false,
 * unsupported, or uncertain is NOT confirmed failure — the request
 * stays with its reservation and original operation intact, and
 * reconciliation must accept legitimate late completion.
 * Submitted/confirmed rows never cancel here; post-payment correction
 * flows through the recovery workflow.
 *
 * The per-request send mutex serializes cancellation against in-flight
 * submits, reconciles, and confirmations, so a cancel can never slip
 * between a provider send and its recording.
 */
final readonly class CancelRewardPayoutAction
{
    use AsAction;

    public function handle(
        RewardPayoutRequest | string $request,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        string $reason,
        ?CarbonInterface $decidedAt = null,
    ): RewardPayoutRequest {
        RewardProgramGuard::findPayoutRequestOrFail($request);

        RewardLedgerEntries::assertActor($actorType, $actorId, $actorRole);
        RewardLedgerEntries::assertReason($reason);

        if ($actorType !== 'user' || ! in_array($actorRole, ['staff', 'admin'], true)) {
            throw new InvalidArgumentException('Payout cancellation requires a staff/admin user identity.');
        }

        $requestId = $request instanceof RewardPayoutRequest ? (string) $request->getKey() : $request;
        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        $pre = RewardPayoutRequest::query()->whereKey($requestId)->first();

        if (! $pre instanceof RewardPayoutRequest) {
            throw new InvalidArgumentException('Unknown reward payout request.');
        }

        if (! in_array($pre->status, [
            RewardPayoutRequestStatus::Requested,
            RewardPayoutRequestStatus::Approved,
            RewardPayoutRequestStatus::Unknown,
        ], true)) {
            throw new InvalidArgumentException('Only requested, approved, or unknown payouts can be cancelled.');
        }

        $preAward = RewardAward::query()->whereKey((string) $pre->award_id)->first();

        if ($preAward instanceof RewardAward && $actorId === (string) $preAward->recipient_id) {
            throw new InvalidArgumentException('Recipient-owned payout cancellation is prohibited.');
        }

        try {
            return Cache::lock('rewards:payout:' . $requestId, 30)->block(10, function () use (
                $requestId,
                $actorType,
                $actorId,
                $actorRole,
                $decided,
            ): RewardPayoutRequest {
                $plan = $this->plan($requestId);

                $providerConfirmed = $plan !== 'provider';

                if ($plan === 'provider') {
                    $providerConfirmed = $this->confirmProviderCancel($requestId);
                }

                return $this->apply($requestId, $plan, $providerConfirmed, $actorType, $actorId, $actorRole, $decided);
            });
        } catch (LockTimeoutException) {
            throw new InvalidArgumentException('Another payout operation for this request is in flight; retry the cancellation.');
        }
    }

    /**
     * Inspects the durable operation under the shared locks and returns
     * the cancellation plan: direct (no provider operation exists), or
     * provider (the provider may hold the operation — its cancel must
     * confirm outside the transaction through the bound executor).
     *
     * @return 'direct'|'provider'
     */
    private function plan(string $requestId): string
    {
        return DB::transaction(function () use ($requestId): string {
            $locked = $this->lockedRequest($requestId);

            if (! in_array($locked->status, [
                RewardPayoutRequestStatus::Requested,
                RewardPayoutRequestStatus::Approved,
                RewardPayoutRequestStatus::Unknown,
            ], true)) {
                throw new InvalidArgumentException('Only requested, approved, or unknown payouts can be cancelled.');
            }

            return $locked->external_payout_id === null ? 'direct' : 'provider';
        });
    }

    private function confirmProviderCancel(string $requestId): bool
    {
        $row = RewardPayoutRequest::query()->whereKey($requestId)->firstOrFail();

        // Provider call outside every transaction, under the send mutex.
        try {
            return app(RewardPayoutExecutor::class)->cancel($row) === true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  'direct'|'provider'  $plan
     */
    private function apply(
        string $requestId,
        string $plan,
        bool $providerConfirmed,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        CarbonImmutable $decided,
    ): RewardPayoutRequest {
        return DB::transaction(function () use (
            $requestId,
            $plan,
            $providerConfirmed,
            $actorType,
            $actorId,
            $actorRole,
            $decided,
        ): RewardPayoutRequest {
            $row = $this->lockedRequest($requestId);

            if (! in_array($row->status, [
                RewardPayoutRequestStatus::Requested,
                RewardPayoutRequestStatus::Approved,
                RewardPayoutRequestStatus::Unknown,
            ], true)) {
                throw new InvalidArgumentException('Only requested, approved, or unknown payouts can be cancelled.');
            }

            if ($plan === 'provider' && ! $providerConfirmed) {
                throw new InvalidArgumentException(
                    'Refused: the provider did not confirm cancellation. The payout keeps its status and reservation; resubmit or reconcile instead.'
                );
            }

            if ($plan !== 'direct') {
                // Provider settlement through the bound executor; the
                // executor records the outcome and never invents one.
                app(RewardPayoutExecutor::class)->settle(
                    $row,
                    'cancelled',
                    ['reference' => (string) ($row->provider_reference ?? $row->first_reference)]
                );
            }

            $total = (int) $row->amount_minor + ((string) $row->fee_payer === 'program' ? (int) $row->fee_minor : 0);

            RewardBudgetLedger::append(
                RewardBudgetMovementKind::Release,
                RewardBudgetLedger::releaseKeyForAttempt(
                    (string) $row->award_id,
                    RewardBudgetLedger::attemptOf($row->evidence),
                    'cancel'
                ),
                (string) $row->program_id,
                (string) $row->currency,
                $total,
                (string) $row->award_id,
                $actorType,
                $actorId,
                $actorRole,
                $decided,
            );

            RewardBudgetLedger::assertNonNegativeLegs(
                RewardBudgetLedger::lockedBalances(
                    (string) $row->program_id,
                    (string) $row->currency,
                    RewardBudgetLedger::lockFunding((string) $row->program_id, (string) $row->currency)
                )
            );

            $row->forceFill([
                'status' => RewardPayoutRequestStatus::Cancelled,
                'cancelled_at' => $decided,
            ])->save();

            return $row->refresh();
        });
    }

    /**
     * Locks the award, request, and funding rows in the shared
     * cross-domain order and returns the locked request.
     */
    private function lockedRequest(string $requestId): RewardPayoutRequest
    {
        $pre = RewardPayoutRequest::query()->whereKey($requestId)->firstOrFail();

        RewardAward::query()->whereKey((string) $pre->award_id)->lockForUpdate()->firstOrFail();

        $row = RewardPayoutRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

        RewardBudgetLedger::lockFunding((string) $row->program_id, (string) $row->currency);

        return $row;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\Rewards\Contracts\RewardPayoutExecution;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Contracts\RewardPayoutMethodCatalog;
use AIArmada\Rewards\Enums\RewardAwardStatus;
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
 * Submit an approved payout through the bound payout rail.
 *
 * Pipeline: verify the frozen fee still matches the live bound schedule,
 * then drive the bound `RewardPayoutExecutor` OUTSIDE any transaction
 * under a per-request send mutex. The executor serves the FROZEN terms
 * (amount, fee, currency, destination) exactly; a later payee change
 * affects future work only. Outcomes map honestly: completed →
 * confirmed (+spend), pending → submitted (manual rails await
 * independent confirmation), unknown → unknown (reservation kept,
 * reconcile later), confirmed failure → failed (+release). Every step
 * resumes from durable rows, so a crash replays without duplicating
 * money.
 */
final readonly class SubmitRewardPayoutAction
{
    use AsAction;

    public function handle(
        RewardPayoutRequest | string $request,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        ?CarbonInterface $decidedAt = null,
    ): RewardPayoutRequest {
        RewardProgramGuard::findPayoutRequestOrFail($request);

        RewardLedgerEntries::assertActor($actorType, $actorId, $actorRole);

        if ($actorType !== 'user' || ! in_array($actorRole, ['staff', 'admin'], true)) {
            throw new InvalidArgumentException('Payout submission requires a staff/admin user identity.');
        }

        $requestId = $request instanceof RewardPayoutRequest ? (string) $request->getKey() : $request;
        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        // Unlocked pre-read for the award id; every lock below is
        // re-verified after acquisition in award -> request order.
        $pre = RewardPayoutRequest::query()->whereKey($requestId)->first();

        if (! $pre instanceof RewardPayoutRequest) {
            throw new InvalidArgumentException('Unknown reward payout request.');
        }

        $awardId = (string) $pre->award_id;

        $preAward = RewardAward::query()->whereKey($awardId)->first();

        if ($preAward instanceof RewardAward && $actorId === (string) $preAward->recipient_id) {
            throw new InvalidArgumentException('Recipient-owned payout submission is prohibited.');
        }

        $locked = DB::transaction(function () use ($requestId, $awardId): RewardPayoutRequest {
            $award = RewardAward::query()->whereKey($awardId)->lockForUpdate()->first();

            if (! $award instanceof RewardAward || $award->status !== RewardAwardStatus::Approved) {
                throw new InvalidArgumentException('Payout submission requires an approved award.');
            }

            $row = RewardPayoutRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if (! in_array($row->status, [RewardPayoutRequestStatus::Approved, RewardPayoutRequestStatus::Unknown], true)) {
                throw new InvalidArgumentException('Only approved or unknown payouts can be submitted.');
            }

            return $row;
        });

        // Gross backing from the FROZEN terms: program-pays-fees backs
        // amount + fee so the bound provider deduction leaves the
        // promised net exactly; recipient-pays backs the award itself.
        $gross = (int) $locked->amount_minor
            + ((string) $locked->fee_payer === 'program' ? (int) $locked->fee_minor : 0);

        $this->assertLiveFeeMatchesFrozen($locked, $gross);

        // The provider call, and the result recording, are mutually
        // exclusive across concurrent submits for this request, so a
        // racing submit can never double-send the operation. Executors
        // with rail-level binding serialize further internally.
        try {
            return Cache::lock('rewards:payout:' . $requestId, 30)->block(10, function () use (
                $requestId,
                $actorType,
                $actorId,
                $actorRole,
                $decided,
            ): RewardPayoutRequest {
                $fresh = RewardPayoutRequest::query()->whereKey($requestId)->firstOrFail();

                if (! in_array($fresh->status, [RewardPayoutRequestStatus::Approved, RewardPayoutRequestStatus::Unknown], true)) {
                    throw new InvalidArgumentException('Only approved or unknown payouts can be submitted.');
                }

                // Provider call outside every transaction. The mutex (not
                // a transaction) spans it.
                $outcome = app(RewardPayoutExecutor::class)->submit($fresh);

                return $this->recordOutcome($requestId, $outcome, $actorType, $actorId, $actorRole, $decided);
            });
        } catch (LockTimeoutException) {
            throw new InvalidArgumentException('Another payout send for this request is in flight; retry the submission.');
        }
    }

    private function recordOutcome(
        string $requestId,
        RewardPayoutExecution $outcome,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        CarbonImmutable $decided,
    ): RewardPayoutRequest {
        return DB::transaction(function () use ($requestId, $outcome, $actorType, $actorId, $actorRole, $decided): RewardPayoutRequest {
            $pre = RewardPayoutRequest::query()->whereKey($requestId)->firstOrFail();

            // Award -> request -> funding: the shared cross-domain order,
            // so spend/release appends serialize with every other funder.
            RewardAward::query()->whereKey((string) $pre->award_id)->lockForUpdate()->firstOrFail();

            $row = RewardPayoutRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            RewardBudgetLedger::lockFunding((string) $row->program_id, (string) $row->currency);

            $this->storeExternalRefs($row, $outcome);

            $status = $outcome->status;
            $reference = $outcome->externalReference;

            if ($status === 'completed') {
                $this->appendSpend($row, $actorType, $actorId, $actorRole, $decided);

                if ($row->status === RewardPayoutRequestStatus::Approved) {
                    // An immediately-completed send still passes through
                    // submitted: the machine forbids skipping it, and the
                    // submitted instant is real provider evidence.
                    $row->forceFill([
                        'status' => RewardPayoutRequestStatus::Submitted,
                        'provider_reference' => $reference ?? $row->provider_reference,
                        'failure_code' => null,
                        'submitted_at' => $decided,
                    ])->save();
                }

                $row->forceFill([
                    'status' => RewardPayoutRequestStatus::Confirmed,
                    'provider_reference' => $reference ?? $row->provider_reference,
                    'failure_code' => null,
                    'confirmed_by' => $actorId,
                    'submitted_at' => $row->submitted_at ?? $decided,
                    'confirmed_at' => $decided,
                ])->save();

                return $row->refresh();
            }

            if ($outcome->isPending()) {
                $fill = [
                    'status' => RewardPayoutRequestStatus::Submitted,
                    'provider_reference' => $reference ?? $row->provider_reference,
                    'failure_code' => null,
                    'submitted_at' => $row->submitted_at ?? $decided,
                ];

                if ($row->submitted_at !== null) {
                    // A later attempt re-submitting: the first stamp
                    // stands; the retry chains into evidence.
                    $evidence = $row->evidence ?? [];
                    $resubmits = $evidence['resubmits'] ?? [];
                    $resubmits[] = [
                        'at' => $decided->toIso8601String(),
                        'attempt' => RewardBudgetLedger::attemptOf($row->evidence),
                        'reference' => $reference ?? $row->provider_reference,
                    ];
                    $evidence['resubmits'] = $resubmits;
                    $fill['evidence'] = $evidence;
                }

                $row->forceFill($fill)->save();

                return $row->refresh();
            }

            if ($outcome->isUnknown()) {
                // Uncertain stays uncertain: keep the reservation and the
                // original operation; reconcile and accept late completion.
                $row->forceFill([
                    'status' => RewardPayoutRequestStatus::Unknown,
                    'provider_reference' => $reference ?? $row->provider_reference,
                    'failure_code' => $outcome->failureCode,
                ])->save();

                return $row->refresh();
            }

            $this->appendRelease($row, $actorType, $actorId, $actorRole, $decided);

            $attempt = RewardBudgetLedger::attemptOf($row->evidence);
            $fill = [
                'status' => RewardPayoutRequestStatus::Failed,
                'failure_code' => $outcome->failureCode,
            ];

            if ($attempt <= 1) {
                $fill['failed_at'] = $decided;
            } else {
                // The first failure stamp stands; later attempts chain.
                $evidence = $row->evidence ?? [];
                $refailures = $evidence['refailures'] ?? [];
                $refailures[] = [
                    'at' => $decided->toIso8601String(),
                    'attempt' => $attempt,
                    'code' => $outcome->failureCode,
                ];
                $evidence['refailures'] = $refailures;
                $fill['evidence'] = $evidence;
            }

            $row->forceFill($fill)->save();

            return $row->refresh();
        });
    }

    /**
     * Stores the executor's external operation identity on first sight.
     * Later executions for the same request must agree with the stored
     * identity; a stranger is refused, never rebound.
     */
    private function storeExternalRefs(RewardPayoutRequest $row, RewardPayoutExecution $outcome): void
    {
        $fill = [];

        foreach ([
            'external_payout_id' => $outcome->externalPayoutId,
            'external_reference_id' => $outcome->externalReferenceId,
            'external_provider' => $outcome->externalProvider,
        ] as $column => $value) {
            if ($value === null) {
                continue;
            }

            $stored = $row->getAttribute($column);

            if ($stored !== null && (string) $stored !== $value) {
                throw new InvalidArgumentException('Payout executor returned a foreign operation identity; refusing the submission.');
            }

            if ($stored === null) {
                $fill[$column] = $value;
            }
        }

        if ($fill !== []) {
            $row->forceFill($fill)->save();
        }
    }

    /**
     * Stable payout reference for this reservation attempt. The frozen
     * first reference is never regenerated; retries after a confirmed
     * failure are distinct provider operations, so attempts past the
     * first derive a distinct reference instead of colliding on the
     * unique payout reference.
     */
    public static function payoutReferenceFor(RewardPayoutRequest $request): string
    {
        $attempt = RewardBudgetLedger::attemptOf($request->evidence);

        return $attempt <= 1
            ? (string) $request->first_reference
            : (string) $request->first_reference . '-A' . $attempt;
    }

    /**
     * The frozen fee must still match the live bound schedule for the
     * frozen backing: a drifted schedule refuses loudly instead of
     * silently underpaying the promised net. Pure read, no HTTP.
     */
    private function assertLiveFeeMatchesFrozen(RewardPayoutRequest $request, int $gross): void
    {
        $live = app(RewardPayoutMethodCatalog::class)
            ->feeFor((string) $request->processor, $gross, (string) $request->currency);

        if ($live !== (int) $request->fee_minor) {
            throw new InvalidArgumentException(
                'Installed fee schedule drifted from the frozen payout terms; refusing the submission.'
            );
        }
    }

    private function appendSpend(
        RewardPayoutRequest $row,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        CarbonImmutable $decided,
    ): void {
        $total = (int) $row->amount_minor + ((string) $row->fee_payer === 'program' ? (int) $row->fee_minor : 0);

        RewardBudgetLedger::append(
            RewardBudgetMovementKind::Spend,
            "spend:{$row->award_id}",
            (string) $row->program_id,
            (string) $row->currency,
            $total,
            (string) $row->award_id,
            $actorType,
            $actorId,
            $actorRole,
            $decided,
        );

        // Spending consumes the reservation; the funded invariant counts
        // reserved and net-spent separately, so both movements append.
        RewardBudgetLedger::append(
            RewardBudgetMovementKind::Release,
            "release:{$row->award_id}:spent",
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
    }

    private function appendRelease(
        RewardPayoutRequest $row,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        CarbonImmutable $decided,
    ): void {
        $total = (int) $row->amount_minor + ((string) $row->fee_payer === 'program' ? (int) $row->fee_minor : 0);

        RewardBudgetLedger::append(
            RewardBudgetMovementKind::Release,
            RewardBudgetLedger::releaseKeyForAttempt(
                (string) $row->award_id,
                RewardBudgetLedger::attemptOf($row->evidence),
                'failure'
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
    }
}

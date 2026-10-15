<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\Rewards\Contracts\RewardCorrectionKeySource;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardMilestoneContribution;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use AIArmada\Rewards\Support\RewardProgramGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Propagate source corrections and confirmed fraud onto live awards.
 *
 * Tainted business keys arrive explicitly — from the bound
 * `RewardCorrectionKeySource` or from the caller — because enumerating
 * corrections and fraud signals is host work core must not scan for.
 * Evaluation-time reads already refuse those keys; this sweep closes
 * the loop for awards that were decided BEFORE the correction arrived,
 * matching the queued recheck semantics exactly: held and pending-review
 * awards reject, approved awards reverse, and milestone contributions
 * invalidate. Fulfilled awards — which the queued job deliberately never
 * touches — reverse through the recovery-aware fulfillment connector
 * (receivable/dispute recoveries open where value already moved). Every
 * applied correction appends ledger entries with the correction
 * evidence, so member timelines, balances, and admin tables all show
 * the adjusted state.
 *
 * The sweep is explicit and never silent: it returns every reversed and
 * rejected award, every opened recovery, every invalidated contribution,
 * every skip, and every per-award error. Re-runs converge (settled work
 * skips), so the scheduled run, an operator rerun, and the queued job
 * all compose safely.
 *
 * Sweeps run without an ambient owner (cron, console, explicit-key
 * callers), so each award transition/reversal and each contribution
 * invalidation runs under the owning program's own owner context.
 * Orphaned owner tuples record a per-award error and never abort the
 * sweep.
 *
 * @return array{reversed: list<string>, rejected: list<string>, recoveries: list<string>, contributions: int, skipped: list<string>, errors: array<string, string>}
 */
final readonly class ApplyRewardSourceCorrectionsAction
{
    use AsAction;

    /**
     * @param  list<array{business_key: string, reason: string, evidence: array<string, mixed>}>|null  $taintedKeys  explicit keys; null resolves from the bound key source.
     * @return array{reversed: list<string>, rejected: list<string>, recoveries: list<string>, contributions: int, skipped: list<string>, errors: array<string, string>}
     */
    public function handle(
        string $actorType,
        ?string $actorId,
        string $actorRole,
        ?CarbonInterface $decidedAt = null,
        int $limit = 200,
        ?array $taintedKeys = null,
    ): array {
        RewardLedgerEntries::assertActor($actorType, $actorId, $actorRole);

        if (! in_array($actorRole, ['staff', 'admin', 'system'], true)) {
            throw new InvalidArgumentException('Applying source corrections requires a staff/admin/system identity.');
        }

        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Correction sweep limit must be 1..1000.');
        }

        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        $report = ['reversed' => [], 'rejected' => [], 'recoveries' => [], 'contributions' => 0, 'skipped' => [], 'errors' => []];

        foreach ($this->taintedKeys($limit, $taintedKeys) as $businessKey => $cause) {
            $awards = RewardAward::query()
                ->where('source_business_key', $businessKey)
                ->orderBy('id')
                ->get();

            foreach ($awards as $award) {
                $awardId = (string) $award->getKey();

                if (in_array($award->status, [
                    RewardAwardStatus::Reversed,
                    RewardAwardStatus::Rejected,
                    RewardAwardStatus::Corrected,
                ], true)) {
                    $report['skipped'][] = $awardId;

                    continue;
                }

                $to = match ($award->status) {
                    RewardAwardStatus::Held,
                    RewardAwardStatus::PendingReview => RewardAwardStatus::Rejected,
                    default => RewardAwardStatus::Reversed,
                };

                try {
                    // Sweeps run without an ambient owner: each award's work
                    // runs under the owning program's own owner.
                    RewardProgramGuard::withProgramOwner((string) $award->program_id, function () use ($awardId, $award, $to, $cause, $actorType, $actorId, $actorRole, $decided, &$report): void {
                        // Same routing as the queued recheck: unapproved work
                        // rejects, approved work reverses, fulfilled work goes
                        // through recovery.
                        if ($award->status === RewardAwardStatus::Fulfilled) {
                            $outcome = ReverseRewardFulfillmentAction::run(
                                $awardId,
                                $actorType,
                                $actorId,
                                $actorRole,
                                $cause['reason'],
                                ['source_correction' => $cause['evidence']],
                                $decided,
                            );

                            foreach ($outcome['recovery_ids'] as $recoveryId) {
                                $report['recoveries'][] = (string) $recoveryId;
                            }
                        } else {
                            app(TransitionRewardAwardAction::class)->handle(
                                $awardId,
                                $to,
                                $actorType,
                                $actorId,
                                $actorRole,
                                $cause['reason'],
                                ['source_correction' => $cause['evidence']],
                                null,
                                $decided,
                            );
                        }

                        $report[$to === RewardAwardStatus::Reversed ? 'reversed' : 'rejected'][] = $awardId;
                    });
                } catch (InvalidArgumentException | AuthorizationException $exception) {
                    // Per-award failures (unknown program, orphaned owner,
                    // refused authorization, concurrent transition) record a
                    // per-award error and let the sweep continue.
                    $report['errors'][$awardId] = $exception->getMessage();
                }
            }

            $contributions = $this->invalidateContributions($businessKey, $actorType, $actorId, $actorRole, $cause['reason'], $decided);
            $report['contributions'] += $contributions['count'];
            $report['errors'] += $contributions['errors'];
        }

        return $report;
    }

    /**
     * @return array{count: int, errors: array<string, string>}
     */
    private function invalidateContributions(
        string $businessKey,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        string $reason,
        CarbonImmutable $decided,
    ): array {
        $contributions = RewardMilestoneContribution::query()
            ->where('source_business_key', $businessKey)
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get();

        $count = 0;
        $errors = [];

        foreach ($contributions as $contribution) {
            try {
                // Sweeps run without an ambient owner: run the invalidation
                // under the cycle's program owner.
                RewardProgramGuard::withProgramOwner(
                    (string) ($contribution->cycle?->program_id ?? ''),
                    static fn () => app(InvalidateRewardMilestoneContributionAction::class)->handle(
                        $contribution,
                        $actorType,
                        $actorId,
                        $actorRole,
                        $reason,
                        $decided,
                    ),
                );
                $count++;
            } catch (InvalidArgumentException | AuthorizationException $exception) {
                // A per-contribution failure — an orphaned owner tuple, a
                // refused authorization, or a concurrent invalidation — is
                // recorded rather than swallowed: the sweep must report every
                // unit it could not settle, or a live milestone contribution
                // would go unnoticed.
                $errors['contribution:' . (string) $contribution->getKey()] = $exception->getMessage();
            }
        }

        return ['count' => $count, 'errors' => $errors];
    }

    /**
     * Tainted source keys: explicit caller keys win; otherwise the bound
     * key source enumerates them. Core never scans a host table here.
     *
     * @param  list<array{business_key: string, reason: string, evidence: array<string, mixed>}>|null  $explicit
     * @return array<string, array{reason: string, evidence: array<string, mixed>}>
     */
    private function taintedKeys(int $limit, ?array $explicit): array
    {
        $tainted = [];

        $keys = $explicit ?? app(RewardCorrectionKeySource::class)->taintedKeys($limit);

        foreach (array_slice($keys, 0, $limit) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = (string) ($entry['business_key'] ?? '');

            if ($key === '' || mb_strlen($key) > 191) {
                continue;
            }

            $tainted[$key] ??= [
                'reason' => (string) ($entry['reason'] ?? 'source-correction'),
                'evidence' => is_array($entry['evidence'] ?? null) ? $entry['evidence'] : ['business_key' => $key],
            ];
        }

        return $tainted;
    }
}

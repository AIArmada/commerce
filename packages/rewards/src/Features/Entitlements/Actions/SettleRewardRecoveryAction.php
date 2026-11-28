<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Actions;

use AIArmada\Rewards\Enums\RewardRecoveryStatus;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Features\Entitlements\Models\RewardRecovery;
use AIArmada\Rewards\Features\Entitlements\Persistence\RewardRecoveryGuard;
use AIArmada\Rewards\Features\Finance\Api\RewardBudgetApi;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Settle a recovery case on confirmed recovery evidence.
 *
 * Settlement requires proof that value actually returned (bank receipt,
 * returned goods, signed writeoff-equivalent acceptance — recorded in
 * evidence, never assumed). A settled cash recovery appends the budget
 * recover movement: the ONLY path that reduces net spent and replenishes
 * available budget. Settling never debits a bank, restores spent points
 * silently, or invents negative balances; point recoveries close the
 * case and leave ledger restoration to explicit linked refunds.
 */
final readonly class SettleRewardRecoveryAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $recoveryEvidence
     */
    public function handle(
        RewardRecovery | string $recovery,
        array $recoveryEvidence,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        ?CarbonInterface $decidedAt = null,
    ): RewardRecovery {
        RewardLedgerEntries::assertActor($actorType, $actorId, $actorRole);

        if ($actorType !== 'user' || ! in_array($actorRole, ['staff', 'admin'], true)) {
            throw new InvalidArgumentException('Recovery settlement requires a staff/admin user identity.');
        }

        $proof = mb_trim((string) ($recoveryEvidence['recovery_proof'] ?? ''));

        if ($proof === '' || mb_strlen($proof) > 191) {
            throw new InvalidArgumentException('Settlement requires recovery_proof evidence of 1..191 characters.');
        }

        $recoveryId = $recovery instanceof RewardRecovery ? (string) $recovery->getKey() : $recovery;
        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        // The program is the tenant boundary: prove the recovery's award's
        // program is in the current owner scope before any lock.
        $resolved = RewardRecoveryGuard::findOrFail($recoveryId);
        $awardId = (string) $resolved->award_id;

        return DB::transaction(function () use ($recoveryId, $awardId, $recoveryEvidence, $proof, $actorType, $actorId, $actorRole, $decided): RewardRecovery {
            $award = RewardAward::query()->whereKey($awardId)->lockForUpdate()->firstOrFail();
            $locked = RewardRecovery::query()->whereKey($recoveryId)->lockForUpdate()->first();

            if (! $locked instanceof RewardRecovery) {
                throw new InvalidArgumentException('Unknown reward recovery.');
            }

            if ((string) $locked->award_id !== $awardId) {
                throw new InvalidArgumentException('Recovery award binding is incoherent.');
            }

            if ($locked->status !== RewardRecoveryStatus::Open) {
                throw new InvalidArgumentException('Only open recoveries can be settled.');
            }

            if ($locked->amount_minor !== null && $locked->currency !== null) {
                $programId = (string) $award->program_id;
                app(RewardBudgetApi::class)->recoverConfirmedSpend(
                    $programId,
                    (string) $locked->currency,
                    (string) $locked->award_id,
                    (int) $locked->amount_minor,
                    (string) $locked->recovery_key,
                    $actorType,
                    $actorId,
                    $actorRole,
                    $decided,
                );
            }

            $evidence = $locked->evidence ?? [];
            $evidence['settlement'] = array_merge($recoveryEvidence, [
                'recovery_proof' => $proof,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_role' => $actorRole,
                'settled_at' => $decided->toIso8601String(),
            ]);

            $locked->forceFill([
                'status' => RewardRecoveryStatus::Settled,
                'evidence' => $evidence,
                'settled_at' => $decided,
            ])->save();

            return $locked->refresh();
        });
    }
}

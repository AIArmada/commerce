<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Actions;

use AIArmada\Rewards\Enums\RewardRecoveryKind;
use AIArmada\Rewards\Enums\RewardRecoveryStatus;
use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitRecoveryApi;
use AIArmada\Rewards\Features\Entitlements\Models\RewardRecovery;
use AIArmada\Rewards\Features\Finance\Api\RewardPayoutQueryApi;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use AIArmada\Rewards\Support\RewardProgramGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Open a post-fulfillment recovery case.
 *
 * The original fulfillment stays immutable; the recovery tracks the
 * receivable/adjustment/dispute with its frozen value snapshot until an
 * authorized settlement or writeoff closes it. Cash recoveries snapshot
 * minor units + currency; point/benefit recoveries snapshot points + unit.
 * Identity is stable per award + source, so replays return the stored
 * case instead of duplicating liability.
 */
final readonly class OpenRewardRecoveryAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function handle(
        RewardAward | string $award,
        RewardRecoveryKind | string $kind,
        ?string $payoutRequest,
        ?string $grant,
        ?int $amountMinor,
        ?string $currency,
        ?int $points,
        ?string $unit,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        string $reason,
        ?array $evidence = null,
        ?CarbonInterface $decidedAt = null,
    ): RewardRecovery {
        RewardProgramGuard::findAwardOrFail($award);

        RewardLedgerEntries::assertActor($actorType, $actorId, $actorRole);
        RewardLedgerEntries::assertReason($reason);

        if ($actorType !== 'user' || ! in_array($actorRole, ['staff', 'admin'], true)) {
            throw new InvalidArgumentException('Recovery cases require a staff/admin user identity.');
        }

        $kindEnum = $kind instanceof RewardRecoveryKind ? $kind : RewardRecoveryKind::tryFrom($kind);

        if (! $kindEnum instanceof RewardRecoveryKind) {
            throw new InvalidArgumentException('Unknown recovery kind.');
        }

        $hasCash = $amountMinor !== null || $currency !== null;
        $hasPoints = $points !== null || $unit !== null;

        $payoutIdEarly = $payoutRequest;
        $grantIdEarly = $grant;
        if ($payoutIdEarly !== null && $grantIdEarly !== null) {
            throw new InvalidArgumentException('Recovery cannot refer to both a payout and a benefit grant.');
        }

        $benefitUnit = ! $hasCash && ! $hasPoints && $grantIdEarly !== null && $kindEnum === RewardRecoveryKind::Dispute;

        if ($hasCash && $hasPoints) {
            throw new InvalidArgumentException('Recovery snapshots either cash value or point value, never both.');
        }

        if (! $hasCash && ! $hasPoints && ! $benefitUnit) {
            throw new InvalidArgumentException('Recovery snapshots cash value, point value, or a consumed benefit unit.');
        }

        if ($hasCash) {
            if ($amountMinor === null || $amountMinor < 1) {
                throw new InvalidArgumentException('Recovery cash amount must be a positive minor-unit amount.');
            }

            if (! is_string($currency) || preg_match('/^[A-Z]{3}$/', mb_strtoupper(mb_trim($currency))) !== 1) {
                throw new InvalidArgumentException('Recovery currency must be a 3-letter ISO-4217 code.');
            }

            $currency = mb_strtoupper(mb_trim($currency));
        }

        if ($hasPoints && ($points === null || $points < 1 || ! is_string($unit) || $unit === '')) {
            throw new InvalidArgumentException('Recovery point value needs positive points and a unit.');
        }

        $awardId = $award instanceof RewardAward ? (string) $award->getKey() : $award;
        $payoutId = $payoutIdEarly;
        $grantId = $grantIdEarly;
        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        $recoveryKey = 'recovery:' . $awardId . ':' . $kindEnum->value . ':' . ($payoutId ?? $grantId ?? 'award');

        if (mb_strlen($recoveryKey) > 191) {
            throw new InvalidArgumentException('Recovery identity exceeds 191 characters.');
        }

        return DB::transaction(function () use (
            $awardId,
            $kindEnum,
            $payoutId,
            $grantId,
            $amountMinor,
            $currency,
            $points,
            $unit,
            $actorType,
            $actorId,
            $actorRole,
            $reason,
            $evidence,
            $decided,
            $recoveryKey,
        ): RewardRecovery {
            $awardRow = RewardAward::query()->whereKey($awardId)->lockForUpdate()->first();

            if (! $awardRow instanceof RewardAward) {
                throw new InvalidArgumentException('Unknown reward award.');
            }

            $replay = RewardRecovery::query()->where('recovery_key', $recoveryKey)->lockForUpdate()->first();

            if ($replay instanceof RewardRecovery) {
                if ($replay->award_id !== $awardId
                    || $replay->kind !== $kindEnum
                    || $replay->payout_request_id !== $payoutId
                    || $replay->grant_id !== $grantId
                    || $replay->amount_minor !== $amountMinor
                    || $replay->currency !== $currency
                    || $replay->points !== $points
                    || $replay->unit !== $unit) {
                    throw new InvalidArgumentException('Recovery identity is already bound to different terms.');
                }

                return $replay;
            }

            if ($payoutId !== null) {
                $payout = app(RewardPayoutQueryApi::class)->forAward($awardId, lock: true);

                if ($payout === null || $payout->id !== $payoutId) {
                    throw new InvalidArgumentException('Recovery payout source must belong to the award.');
                }
            }

            if ($grantId !== null) {
                app(RewardBenefitRecoveryApi::class)->assertGrantBelongsToAward($grantId, $awardId);
            }

            return RewardRecovery::query()->create([
                'recovery_key' => $recoveryKey,
                'award_id' => $awardId,
                'payout_request_id' => $payoutId,
                'grant_id' => $grantId,
                'kind' => $kindEnum,
                'status' => RewardRecoveryStatus::Open,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'points' => $points,
                'unit' => $unit,
                'evidence' => array_merge($evidence ?? [], ['reason' => $reason]),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_role' => $actorRole,
                'opened_at' => $decided,
            ]);
        });
    }
}

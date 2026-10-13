<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Actions;

use AIArmada\Rewards\Contracts\RewardBenefitCouponFulfillment;
use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Models\RewardBenefitGrant;
use AIArmada\Rewards\Models\RewardPointReservationSubmission;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use AIArmada\Rewards\Support\RewardProgramGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Record actual benefit use: issued → consumed, exactly once.
 *
 * Manual grants consume on recipient/staff use evidence. Coupon grants
 * consume ONLY on canonical coupon usage proven through the bound
 * coupon contract: the evidence may cite the recorded usage, or omit
 * it to auto-resolve the recipient's single canonical usage for the
 * issued code. The usage is verified to belong to the coupon AND the
 * grant recipient — no matching usage, no consumption. Issuance is
 * never confused with use.
 */
final readonly class ConsumeRewardBenefitAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $useEvidence
     */
    public function handle(
        RewardBenefitGrant | string $grant,
        array $useEvidence,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        ?CarbonInterface $decidedAt = null,
    ): RewardBenefitGrant {
        RewardProgramGuard::findGrantOrFail($grant);

        RewardLedgerEntries::assertActor($actorType, $actorId, $actorRole);

        if ($actorType !== 'user' || ! in_array($actorRole, ['member', 'staff', 'admin'], true)) {
            throw new InvalidArgumentException('Benefit consumption requires a user identity.');
        }

        $grantId = $grant instanceof RewardBenefitGrant ? (string) $grant->getKey() : $grant;
        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        return DB::transaction(function () use ($grantId, $useEvidence, $actorId, $actorRole, $decided): RewardBenefitGrant {
            $locked = RewardBenefitGrant::query()->whereKey($grantId)->lockForUpdate()->first();

            if (! $locked instanceof RewardBenefitGrant) {
                throw new InvalidArgumentException('Unknown reward benefit grant.');
            }

            if ($locked->status !== RewardBenefitGrantStatus::Issued) {
                throw new InvalidArgumentException('Only issued grants can be consumed.');
            }

            if ($actorRole === 'member' && $actorId !== (string) $locked->recipient_id) {
                throw new InvalidArgumentException('Members consume only their own benefits.');
            }

            if ((string) $locked->fulfillment_adapter === 'coupon') {
                $this->assertCanonicalUsage($locked, $useEvidence);
            } else {
                $useRef = mb_trim((string) ($useEvidence['use_reference'] ?? ''));

                if ($useRef === '' || mb_strlen($useRef) > 191) {
                    throw new InvalidArgumentException('Manual consumption requires use_reference evidence of 1..191 characters.');
                }
            }

            if ($locked->reservation_key !== null
                && (string) $locked->fulfillment_adapter === 'coupon'
            ) {
                MarkRewardReservationSubmittedAction::run(
                    (string) $locked->reservation_key,
                    RewardPointReservationSubmission::STATUS_DELIVERED,
                    (string) $locked->external_ref,
                    $decided,
                );
            }

            $evidence = $locked->evidence ?? [];
            $evidence['consumption'] = $useEvidence;

            $locked->forceFill([
                'status' => RewardBenefitGrantStatus::Consumed,
                'evidence' => $evidence,
                'consumed_at' => $decided,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * Canonical coupon usage through the bound coupon contract: the cited
     * (or auto-resolved) usage must belong to the issued coupon AND to
     * the grant recipient. Issuance is never confused with use.
     *
     * @param  array<string, mixed>  $useEvidence
     */
    private function assertCanonicalUsage(RewardBenefitGrant $grant, array $useEvidence): void
    {
        app(RewardBenefitCouponFulfillment::class)->assertCanonicalUsage($grant, $useEvidence);
    }
}

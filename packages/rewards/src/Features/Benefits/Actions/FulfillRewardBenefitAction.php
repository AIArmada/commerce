<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Actions;

use AIArmada\Rewards\Contracts\RewardBenefitCouponFulfillment;
use AIArmada\Rewards\Contracts\RewardRecipientResolver;
use AIArmada\Rewards\Data\RewardReservationSubmissionStatus;
use AIArmada\Rewards\Enums\RewardBenefitGrantStatus;
use AIArmada\Rewards\Enums\RewardBenefitStockKind;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitGrant;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitItem;
use AIArmada\Rewards\Features\Benefits\Persistence\RewardBenefitGuard;
use AIArmada\Rewards\Features\Benefits\Persistence\RewardBenefitStockLedger;
use AIArmada\Rewards\Features\Points\Api\RewardPointsApi;
use AIArmada\Rewards\Queries\RewardCouponMapper;
use AIArmada\Rewards\Support\RewardLedgerEntries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fulfill a reserved grant: capture the held points, consume the held
 * stock, and issue exactly once.
 *
 * Manual adapter: the operator presents handoff evidence (who received
 * what, which reference) and the grant issues; the reservation closes as
 * delivered. Coupon adapter: the item's frozen fixed-value voucher terms
 * issue a recipient-bound single-use voucher into the recipient's wallet
 * (issuance consumes once; canonical usage is recorded only on actual
 * use via ConsumeRewardBenefitAction). Fulfill refuses on expired items —
 * the operator expires or cancels the grant instead.
 */
final readonly class FulfillRewardBenefitAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $handoffEvidence
     */
    public function handle(
        RewardBenefitGrant | string $grant,
        array $handoffEvidence,
        string $actorType,
        ?string $actorId,
        string $actorRole,
        ?CarbonInterface $decidedAt = null,
    ): RewardBenefitGrant {
        RewardBenefitGuard::findGrantOrFail($grant);

        RewardLedgerEntries::assertActor($actorType, $actorId, $actorRole);

        if ($actorType !== 'user' || ! in_array($actorRole, ['staff', 'admin'], true)) {
            throw new InvalidArgumentException('Benefit fulfillment requires a staff/admin user identity.');
        }

        $grantId = $grant instanceof RewardBenefitGrant ? (string) $grant->getKey() : $grant;
        $decided = $decidedAt === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse($decidedAt)->setTimezone('UTC');

        return DB::transaction(function () use ($grantId, $handoffEvidence, $actorType, $actorId, $actorRole, $decided): RewardBenefitGrant {
            $locked = RewardBenefitGrant::query()->whereKey($grantId)->lockForUpdate()->first();

            if (! $locked instanceof RewardBenefitGrant) {
                throw new InvalidArgumentException('Unknown reward benefit grant.');
            }

            if ($locked->status !== RewardBenefitGrantStatus::Reserved) {
                throw new InvalidArgumentException('Only reserved grants can be fulfilled.');
            }

            $item = RewardBenefitItem::query()->whereKey((string) $locked->item_id)->lockForUpdate()->firstOrFail();

            if ($item->expires_at !== null && ! $item->expires_at->greaterThan($decided)) {
                throw new InvalidArgumentException('Benefit item has expired; expire or cancel the grant instead.');
            }

            $externalRef = null;

            if ((string) $locked->fulfillment_adapter === 'coupon') {
                $externalRef = $this->issueCoupon($locked, $item, $decided);
            } else {
                $reference = mb_trim((string) ($handoffEvidence['handoff_reference'] ?? ''));

                if ($reference === '' || mb_strlen($reference) > 191) {
                    throw new InvalidArgumentException('Manual fulfillment requires handoff_reference evidence of 1..191 characters.');
                }

                $externalRef = $reference;
            }

            if ($locked->reservation_key !== null) {
                $this->captureReservation($locked, $decided, $actorType, $actorId, $externalRef);
            }

            RewardBenefitStockLedger::append(
                RewardBenefitStockKind::Consume,
                "stock-consume:{$locked->grant_key}",
                (string) $item->getKey(),
                (int) $locked->quantity,
                (string) $locked->getKey(),
                $actorType,
                $actorId,
                $actorRole,
                $decided,
            );

            $evidence = $locked->evidence ?? [];
            $evidence['fulfillment'] = $handoffEvidence;

            $locked->forceFill([
                'status' => RewardBenefitGrantStatus::Issued,
                'external_ref' => $externalRef,
                'evidence' => $evidence,
                'issued_at' => $decided,
            ])->save();

            return $locked->refresh();
        });
    }

    private function captureReservation(
        RewardBenefitGrant $grant,
        CarbonImmutable $decided,
        string $actorType,
        ?string $actorId,
        string $externalRef,
    ): void {
        app(RewardPointsApi::class)->capture(
            (string) $grant->reservation_key,
            (string) $grant->recipient_type,
            (string) $grant->recipient_id,
            "capture:{$grant->reservation_key}",
            "benefit-fulfillment:{$grant->grant_key}",
            $decided,
            $actorType,
            $actorId,
        );

        app(RewardPointsApi::class)->markSubmission(
            (string) $grant->reservation_key,
            (string) $grant->fulfillment_adapter === 'coupon'
                ? RewardReservationSubmissionStatus::Submitted->value
                : RewardReservationSubmissionStatus::Delivered->value,
            $externalRef,
            $decided,
        );
    }

    private function issueCoupon(
        RewardBenefitGrant $grant,
        RewardBenefitItem $item,
        CarbonImmutable $decided,
    ): string {
        $config = $item->fulfillment_config['voucher'] ?? null;

        if (! is_array($config)) {
            throw new InvalidArgumentException('Coupon fulfillment requires frozen voucher terms on the catalog item.');
        }

        $allowed = RewardLedgerEntries::recipientTypes();

        if (! in_array((string) $grant->recipient_type, $allowed, true)) {
            throw new InvalidArgumentException('Coupon recipient type must be one of [' . implode(',', $allowed) . '].');
        }

        if (! app(RewardRecipientResolver::class)->exists((string) $grant->recipient_type, (string) $grant->recipient_id)) {
            throw new InvalidArgumentException('Coupon recipient is missing.');
        }

        // Recipient-bound issuance through the bound coupon contract.
        // Validation and usage recording refuse any other account.
        return app(RewardBenefitCouponFulfillment::class)->issue(RewardCouponMapper::grant($grant), RewardCouponMapper::terms($item), $decided);
    }
}

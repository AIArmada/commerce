<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\BalanceReader;
use AIArmada\Affiliates\Features\Conversions\Api\BalanceSnapshot;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSelection;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutReservation;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\States\PayoutStatus;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfile;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Create a new payout from affiliate conversions.
 */
final readonly class CreatePayout
{
    public function __construct(
        private PayoutReservation $reservation,
        private BalanceReader $balances,
        private PayoutConversionSelection $selection,
    ) {}

    /**
     * Create a payout from the given conversion IDs.
     *
     * Only approved, unlinked conversions may be paid, and they must all share
     * one currency: each payout reserves exactly one per-currency balance, so
     * mixed-currency sets are rejected instead of summed silently.
     *
     * @param  array<int, string>  $conversionIds
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $conversionIds, array $attributes = [], ?string $expectedAffiliateId = null): AffiliatePayout
    {
        $conversionIds = array_values(array_filter($conversionIds));

        if ($conversionIds === []) {
            throw new InvalidArgumentException('At least one unpaid conversion is required to create a payout.');
        }

        if (count($conversionIds) !== count(array_unique($conversionIds))) {
            throw new InvalidArgumentException('A payout requires unique conversion IDs.');
        }

        $connection = (new AffiliatePayout)->getConnection();
        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($connection));

        foreach ([new AffiliatePayout, new AffiliatePayoutOperation, new AffiliatePayoutHold] as $model) {
            if ($model->getConnection() !== $connection) {
                throw new LogicException('Payout reservation requires a shared database connection.');
            }
        }

        return $connection->transaction(function () use ($conversionIds, $attributes, $expectedAffiliateId, $connection): AffiliatePayout {
            $selection = $this->selection->manual($conversionIds, spl_object_id($connection));
            $affiliate = app(AffiliateProfileReader::class)->lock($selection->affiliateId, spl_object_id($connection))
                ?? throw new InvalidArgumentException('The selected affiliate is missing or inaccessible.');

            if (config('affiliates.owner.enabled', false)
                && ($affiliate->reference->ownerType !== $selection->ownerType || $affiliate->reference->ownerId !== $selection->ownerId)) {
                throw new InvalidArgumentException('Payout conversion owner must match the affiliate.');
            }

            if ($expectedAffiliateId !== null && $affiliate->reference->id !== $expectedAffiliateId) {
                throw new InvalidArgumentException('The conversions do not belong to the selected affiliate.');
            }

            if (! $affiliate->commercial
                && (! $selection->onlyRewardAwards || ! (bool) config('affiliates.payouts.reward_award_settlement.enabled', true))) {
                throw new InvalidArgumentException('Only commercial-purpose conversions may be paid unless reward-award settlement is explicitly enabled.');
            }

            if (AffiliatePayoutHold::query()->where('affiliate_id', $affiliate->reference->id)->active()->exists()) {
                throw new InvalidArgumentException('An active payout hold blocks payout reservation.');
            }

            $this->assertPayeeMatchesAffiliate($affiliate, $attributes);
            $payoutOverride = $this->resolvePayoutOverride($affiliate, $attributes);

            $ownerType = $selection->ownerType;
            $ownerId = $selection->ownerId;

            $this->assertOwnerAttributesMatch($attributes, $ownerType, $ownerId);

            $currency = $selection->currency;

            if (array_key_exists('currency', $attributes) && $attributes['currency'] !== null
                && mb_strtoupper((string) $attributes['currency']) !== $currency) {
                throw new InvalidArgumentException('Payout currency must match the conversions being paid.');
            }

            $total = $selection->totalMinor;

            if ($total <= 0) {
                throw new InvalidArgumentException('Payout total must be greater than zero.');
            }

            $balance = $this->reserveBalance($affiliate, $total, $currency, spl_object_id($connection));
            $reference = $attributes['reference'] ?? $this->generateReference();

            // Handle status - accept either enum or string. Terminal states are
            // rejected: conversions and balance sync happen through status
            // transitions after creation, never at creation time.
            $status = $attributes['status'] ?? PendingPayout::class;
            $status = PayoutStatus::fromString($status);

            if (! $status->equals(PendingPayout::class) && ! $status->equals(ProcessingPayout::class)) {
                throw new InvalidArgumentException('A payout can only be created as pending or processing.');
            }

            $sequence = $balance->payoutSequence + 1;

            $operation = new AffiliatePayoutOperation([
                'affiliate_id' => $selection->affiliateId,
                'operation_key' => sprintf('manual:%s:%s:%d', $selection->affiliateId, $currency, $sequence),
                'status' => 'claimed',
                'amount_minor' => $total,
                'currency' => $currency,
                'payout_sequence' => $sequence,
                'claimed_at' => CarbonImmutable::now(),
                'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
            ]);
            $operation->forceFill(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
            $operation->save();

            $payout = new AffiliatePayout([
                'affiliate_payout_operation_id' => $operation->id,
                'reference' => $reference,
                'status' => $status::class,
                'total_minor' => $total,
                'conversion_count' => $selection->count,
                'currency' => $currency,
                'metadata' => $this->payoutMetadata($attributes, $payoutOverride),
                'payee_type' => $affiliate->awardRecipient->morphType,
                'payee_id' => $affiliate->reference->id,
                'scheduled_at' => $attributes['scheduled_at'] ?? null,
                'paid_at' => $attributes['paid_at'] ?? null,
            ]);
            $payout->forceFill(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
            $payout->save();

            $this->reservation->reserve(
                $balance->id,
                $affiliate->reference->id,
                (string) $payout->getKey(),
                $currency,
                $total,
                $sequence,
                $conversionIds,
            );

            $operation->forceFill([
                'affiliate_payout_id' => $payout->id,
                'status' => 'reserved',
            ])->save();

            return $payout;
        }, attempts: 3);
    }

    /**
     * Enforce affiliate payout eligibility.
     *
     * Pending affiliates were never approved: approval (not an
     * override) is the unblock path. Paused affiliates are reversibly
     * halted: unpause to pay. Only disabled/closed affiliates —
     * terminal, with legitimately earned balances and no other release
     * path — accept an audited override reason.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null The recorded override, or null when none was needed.
     */
    private function resolvePayoutOverride(AffiliateProfile $affiliate, array $attributes): ?array
    {
        if ($affiliate->canReceivePayout) {
            return null;
        }

        $reason = $attributes['payout_override_reason'] ?? null;

        if ($affiliate->status !== 'disabled' || ! is_string($reason) || mb_trim($reason) === '') {
            throw new InvalidArgumentException(sprintf(
                'Affiliate "%s" cannot receive payouts (status: %s). Pending affiliates must be approved first; paused affiliates must be unpaused; disabled affiliates require a payout_override_reason to release earned balances with an audited reason.',
                (string) $affiliate->code,
                $affiliate->status,
            ));
        }

        return [
            'reason' => mb_trim($reason),
            'affiliate_status' => $affiliate->status,
            'overridden_at' => CarbonImmutable::now()->toIso8601String(),
            'overridden_by' => auth()->user()?->getAuthIdentifier(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $payoutOverride
     */
    private function payoutMetadata(array $attributes, ?array $payoutOverride): ?array
    {
        $metadata = $attributes['metadata'] ?? [];

        // Reserved key: only the validated override below may set it, so
        // caller-supplied metadata can never forge an audited override.
        unset($metadata['payout_override']);

        if ($payoutOverride !== null) {
            $metadata['payout_override'] = $payoutOverride;
        }

        return $metadata === [] ? null : $metadata;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertOwnerAttributesMatch(array $attributes, ?string $ownerType, ?string $ownerId): void
    {
        foreach (['owner_type' => $ownerType, 'owner_id' => $ownerId] as $key => $expected) {
            if (! array_key_exists($key, $attributes) || $attributes[$key] === null) {
                continue;
            }

            if ((string) $attributes[$key] !== (string) $expected) {
                throw new InvalidArgumentException('Payout owner must match the conversions being paid.');
            }
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertPayeeMatchesAffiliate(AffiliateProfile $affiliate, array $attributes): void
    {
        $type = $attributes['payee_type'] ?? $affiliate->awardRecipient->morphType;
        $id = $attributes['payee_id'] ?? $affiliate->reference->id;

        if (! is_string($type)
            || ! app(AffiliateProfileReader::class)->matchesMorphType($type)
            || ! is_string($id)
            || $id !== $affiliate->reference->id) {
            throw new InvalidArgumentException('Payout payee must match the affiliate whose conversions are reserved.');
        }
    }

    private function reserveBalance(AffiliateProfile $affiliate, int $total, string $currency, int $connectionIdentity): BalanceSnapshot
    {
        $balance = $this->balances->lock($affiliate->reference->id, $currency, $connectionIdentity);

        if ($balance === null || $balance->availableMinor < $total) {
            throw new InvalidArgumentException('Affiliate available balance does not cover the payout total.');
        }

        return $balance;
    }

    private function generateReference(): string
    {
        $prefix = (string) config('affiliates.payouts.reference_prefix', 'PO-');

        return $prefix . Str::upper(Str::random(10));
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\BalanceReader;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSelection;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutReservation;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Carbon\CarbonImmutable;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class ClaimScheduledPayout
{
    use AsAction;

    public function __construct(
        private readonly PayoutReservation $reservation,
        private readonly BalanceReader $balances,
        private readonly PayoutConversionSelection $selection,
    ) {}

    public function isEligibleSnapshot(string $affiliateId, int $minimumAmountMinor, ?string $currency = null): bool
    {
        $connectionIdentity = spl_object_id((new AffiliatePayout)->getConnection());
        $affiliate = app(AffiliateProfileReader::class)->find(
            $affiliateId,
            $connectionIdentity,
            includeGlobal: ! OwnerScopeOverride::suppressIncludeGlobal() && (bool) config('affiliates.owner.include_global', false),
        );
        $currency = mb_strtoupper($currency ?? $affiliate?->currency ?? (string) config('affiliates.currency.default', 'MYR'));

        if ($affiliate === null || ! $affiliate->commercial || ! $affiliate->canReceivePayout) {
            return false;
        }

        $balance = $this->balances->find($affiliateId, $currency);

        if ($balance === null) {
            return false;
        }

        $threshold = max($minimumAmountMinor, $balance->minimumPayoutMinor);

        if ($balance->availableMinor < $threshold || $balance->availableMinor <= 0) {
            return false;
        }

        if (AffiliatePayoutHold::query()->where('affiliate_id', $affiliate->reference->id)->active()
            ->exists()) {
            return false;
        }

        if (AffiliatePayout::query()->forOwner()->where('payee_type', $affiliate->awardRecipient->morphType)->where('payee_id', $affiliateId)
            ->where('currency', $currency)
            ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
            ->exists()) {
            return false;
        }

        $allocation = $this->selection->scheduled($affiliateId, $balance->availableMinor, $currency, false, $connectionIdentity);

        return $allocation->conversionIds !== [] && $allocation->amountMinor >= $threshold;
    }

    public function handle(string $affiliateId, int $minimumAmountMinor, ?string $currency = null): ?AffiliatePayoutOperation
    {
        $connection = (new AffiliatePayout)->getConnection();
        $connectionIdentity = spl_object_id($connection);
        app(AffiliateAccessGuard::class)->assertConnection($connectionIdentity);

        foreach ([new AffiliatePayout, new AffiliatePayoutOperation, new AffiliatePayoutEvent, new AffiliatePayoutHold] as $model) {
            if ($model->getConnection() !== $connection) {
                throw new LogicException('Scheduled payout reservation requires a shared database connection.');
            }
        }

        return $connection->transaction(function () use ($affiliateId, $minimumAmountMinor, $currency, $connectionIdentity): ?AffiliatePayoutOperation {
            $preview = app(AffiliateProfileReader::class)->find(
                $affiliateId,
                $connectionIdentity,
                includeGlobal: ! OwnerScopeOverride::suppressIncludeGlobal() && (bool) config('affiliates.owner.include_global', false),
            );

            if ($preview === null) {
                return null;
            }

            $owner = OwnerContext::fromTypeAndId($preview->reference->ownerType, $preview->reference->ownerId);

            return OwnerContext::withOwner($owner, function () use ($affiliateId, $minimumAmountMinor, $currency, $connectionIdentity, $owner): ?AffiliatePayoutOperation {
                $affiliate = app(AffiliateProfileReader::class)->lock($affiliateId, $connectionIdentity, includeGlobal: $owner === null);
                $currency = mb_strtoupper($currency ?? $affiliate?->currency ?? (string) config('affiliates.currency.default', 'MYR'));

                if ($affiliate === null || ! $affiliate->commercial || ! $affiliate->canReceivePayout) {
                    return null;
                }

                $balance = $this->balances->lock($affiliateId, $currency, $connectionIdentity);

                if ($balance === null) {
                    return null;
                }

                $threshold = max($minimumAmountMinor, $balance->minimumPayoutMinor);

                if ($balance->availableMinor < $threshold || $balance->availableMinor <= 0) {
                    return null;
                }

                $activeHold = AffiliatePayoutHold::query()->where('affiliate_id', $affiliate->reference->id)->active()
                    ->lockForUpdate()
                    ->first();

                if ($activeHold !== null) {
                    return null;
                }

                $pending = AffiliatePayout::query()->forOwner()->where('payee_type', $affiliate->awardRecipient->morphType)->where('payee_id', $affiliateId)
                    ->where('currency', $currency)
                    ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
                    ->lockForUpdate()
                    ->first();

                if ($pending !== null) {
                    return null;
                }

                $allocation = $this->selection->scheduled(
                    affiliateId: $affiliateId,
                    availableMinor: $balance->availableMinor,
                    currency: $currency,
                    lockForUpdate: true,
                    connectionIdentity: $connectionIdentity,
                );
                $conversionIds = $allocation->conversionIds;
                $amountMinor = $allocation->amountMinor;

                if ($conversionIds === [] || $amountMinor < $threshold) {
                    return null;
                }

                $sequence = $balance->payoutSequence + 1;
                $operation = new AffiliatePayoutOperation([
                    'affiliate_id' => $affiliate->reference->id,
                    'operation_key' => sprintf('scheduled:%s:%s:%d', $affiliate->reference->id, $currency, $sequence),
                    'status' => 'claimed',
                    'amount_minor' => $amountMinor,
                    'currency' => mb_strtoupper($balance->currency),
                    'payout_sequence' => $sequence,
                    'claimed_at' => CarbonImmutable::now(),
                    'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
                ]);
                $operation->forceFill([
                    'owner_type' => $affiliate->reference->ownerType,
                    'owner_id' => $affiliate->reference->ownerId,
                ]);
                $operation->save();

                $payout = new AffiliatePayout([
                    'affiliate_payout_operation_id' => $operation->id,
                    'reference' => 'PAY-' . mb_strtoupper(str_replace('-', '', $operation->id)),
                    'payee_type' => $affiliate->awardRecipient->morphType,
                    'payee_id' => $affiliate->reference->id,
                    'total_minor' => $amountMinor,
                    'conversion_count' => count($conversionIds),
                    'currency' => mb_strtoupper($balance->currency),
                    'status' => PendingPayout::value(),
                    'scheduled_at' => CarbonImmutable::now(),
                ]);
                $payout->forceFill([
                    'owner_type' => $affiliate->reference->ownerType,
                    'owner_id' => $affiliate->reference->ownerId,
                ]);
                $payout->save();

                $this->reservation->reserve(
                    $balance->id,
                    $affiliate->reference->id,
                    (string) $payout->getKey(),
                    $currency,
                    $amountMinor,
                    $sequence,
                    $conversionIds,
                );

                AffiliatePayoutEvent::query()->create([
                    'affiliate_payout_id' => $payout->getKey(),
                    'to_status' => PendingPayout::value(),
                    'notes' => 'Payout reserved by atomic scheduled operation ' . $operation->id,
                ]);

                $operation->forceFill([
                    'affiliate_payout_id' => $payout->id,
                    'status' => 'reserved',
                ])->save();

                return $operation->refresh();
            });
        }, attempts: 3);
    }
}

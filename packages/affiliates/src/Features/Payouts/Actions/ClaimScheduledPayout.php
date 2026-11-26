<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\BalanceReader;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSelection;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutReservation;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
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
        $currency = mb_strtoupper($currency ?? $this->defaultCurrency($affiliateId));
        $affiliate = Affiliate::query()->forOwner()->find($affiliateId);

        if (! $affiliate instanceof Affiliate || ! $affiliate->isCommercialPurpose() || ! $affiliate->canReceivePayout() || $affiliate->isDeactivated()) {
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

        if (AffiliatePayoutHold::query()->where('affiliate_id', $affiliate->getKey())->active()
            ->exists()) {
            return false;
        }

        if (AffiliatePayout::query()->forOwner()->where('payee_type', $affiliate->getMorphClass())->where('payee_id', $affiliateId)
            ->where('currency', $currency)
            ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
            ->exists()) {
            return false;
        }

        $allocation = $this->selection->scheduled($affiliateId, $balance->availableMinor, $currency, false, spl_object_id($affiliate->getConnection()));

        return $allocation->conversionIds !== [] && $allocation->amountMinor >= $threshold;
    }

    public function handle(string $affiliateId, int $minimumAmountMinor, ?string $currency = null): ?AffiliatePayoutOperation
    {
        $currency = mb_strtoupper($currency ?? $this->defaultCurrency($affiliateId));

        $connection = (new Affiliate)->getConnection();

        foreach ([new AffiliatePayout, new AffiliatePayoutOperation, new AffiliatePayoutEvent, new AffiliatePayoutHold] as $model) {
            if ($model->getConnection() !== $connection) {
                throw new LogicException('Scheduled payout reservation requires a shared database connection.');
            }
        }

        return $connection->transaction(function () use ($affiliateId, $minimumAmountMinor, $currency): ?AffiliatePayoutOperation {
            $affiliate = Affiliate::query()->forOwner()->lockForUpdate()->find($affiliateId);

            if (! $affiliate instanceof Affiliate || ! $affiliate->isCommercialPurpose() || ! $affiliate->canReceivePayout() || $affiliate->isDeactivated()) {
                return null;
            }

            $balance = $this->balances->lock($affiliateId, $currency, spl_object_id($affiliate->getConnection()));

            if ($balance === null) {
                return null;
            }

            $threshold = max($minimumAmountMinor, $balance->minimumPayoutMinor);

            if ($balance->availableMinor < $threshold || $balance->availableMinor <= 0) {
                return null;
            }

            $activeHold = AffiliatePayoutHold::query()->where('affiliate_id', $affiliate->getKey())->active()
                ->lockForUpdate()
                ->first();

            if ($activeHold !== null) {
                return null;
            }

            $pending = AffiliatePayout::query()->forOwner()->where('payee_type', $affiliate->getMorphClass())->where('payee_id', $affiliateId)
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
                connectionIdentity: spl_object_id($affiliate->getConnection()),
            );
            $conversionIds = $allocation->conversionIds;
            $amountMinor = $allocation->amountMinor;

            if ($conversionIds === [] || $amountMinor < $threshold) {
                return null;
            }

            $sequence = $balance->payoutSequence + 1;
            $operation = new AffiliatePayoutOperation([
                'affiliate_id' => $affiliate->id,
                'operation_key' => sprintf('scheduled:%s:%s:%d', $affiliate->id, $currency, $sequence),
                'status' => 'claimed',
                'amount_minor' => $amountMinor,
                'currency' => mb_strtoupper($balance->currency),
                'payout_sequence' => $sequence,
                'claimed_at' => CarbonImmutable::now(),
                'lease_expires_at' => CarbonImmutable::now()->addMinutes(5),
            ]);
            $operation->forceFill([
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
            ]);
            $operation->save();

            $payout = new AffiliatePayout([
                'affiliate_payout_operation_id' => $operation->id,
                'reference' => 'PAY-' . mb_strtoupper(str_replace('-', '', $operation->id)),
                'payee_type' => $affiliate->getMorphClass(),
                'payee_id' => $affiliate->id,
                'total_minor' => $amountMinor,
                'conversion_count' => count($conversionIds),
                'currency' => mb_strtoupper($balance->currency),
                'status' => PendingPayout::value(),
                'scheduled_at' => CarbonImmutable::now(),
            ]);
            $payout->forceFill([
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
            ]);
            $payout->save();

            $this->reservation->reserve(
                $balance->id,
                (string) $affiliate->getKey(),
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
        }, attempts: 3);
    }

    private function defaultCurrency(string $affiliateId): string
    {
        $affiliateCurrency = Affiliate::query()->forOwner()->whereKey($affiliateId)->value('currency');

        if (is_string($affiliateCurrency) && $affiliateCurrency !== '') {
            return $affiliateCurrency;
        }

        return (string) config('affiliates.currency.default', 'MYR');
    }
}

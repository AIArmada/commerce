<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutReservation;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use Carbon\CarbonImmutable;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class ClaimScheduledPayout
{
    use AsAction;

    public function __construct(private readonly PayoutReservation $reservation) {}

    public function isEligibleSnapshot(string $affiliateId, int $minimumAmountMinor, ?string $currency = null): bool
    {
        $currency = mb_strtoupper($currency ?? $this->defaultCurrency($affiliateId));
        $affiliate = Affiliate::query()->forOwner()->find($affiliateId);

        if (! $affiliate instanceof Affiliate || ! $affiliate->isCommercialPurpose() || ! $affiliate->canReceivePayout() || $affiliate->isDeactivated()) {
            return false;
        }

        $balance = AffiliateBalance::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('currency', $currency)
            ->first();

        if (! $balance instanceof AffiliateBalance) {
            return false;
        }

        $threshold = max($minimumAmountMinor, $balance->minimum_payout_minor);

        if ($balance->available_minor < $threshold || $balance->available_minor <= 0) {
            return false;
        }

        if (AffiliatePayoutHold::query()->where('affiliate_id', $affiliate->getKey())->active()
            ->exists()) {
            return false;
        }

        if ($affiliate->payouts()
            ->where('currency', $currency)
            ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
            ->exists()) {
            return false;
        }

        $allocation = $this->fundedConversionAllocation($affiliate, $balance->available_minor, $currency);

        return $allocation['conversion_ids'] !== [] && $allocation['amount_minor'] >= $threshold;
    }

    public function handle(string $affiliateId, int $minimumAmountMinor, ?string $currency = null): ?AffiliatePayoutOperation
    {
        $currency = mb_strtoupper($currency ?? $this->defaultCurrency($affiliateId));

        $connection = (new Affiliate)->getConnection();

        foreach ([new AffiliateBalance, new AffiliateConversion, new AffiliatePayout, new AffiliatePayoutOperation, new AffiliatePayoutEvent, new AffiliatePayoutHold] as $model) {
            if ($model->getConnection() !== $connection) {
                throw new LogicException('Scheduled payout reservation requires a shared database connection.');
            }
        }

        return $connection->transaction(function () use ($affiliateId, $minimumAmountMinor, $currency): ?AffiliatePayoutOperation {
            $affiliate = Affiliate::query()->forOwner()->lockForUpdate()->find($affiliateId);

            if (! $affiliate instanceof Affiliate || ! $affiliate->isCommercialPurpose() || ! $affiliate->canReceivePayout() || $affiliate->isDeactivated()) {
                return null;
            }

            $balance = AffiliateBalance::query()
                ->where('affiliate_id', $affiliate->id)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $balance instanceof AffiliateBalance) {
                return null;
            }

            $threshold = max($minimumAmountMinor, $balance->minimum_payout_minor);

            if ($balance->available_minor < $threshold || $balance->available_minor <= 0) {
                return null;
            }

            $activeHold = AffiliatePayoutHold::query()->where('affiliate_id', $affiliate->getKey())->active()
                ->lockForUpdate()
                ->first();

            if ($activeHold !== null) {
                return null;
            }

            $pending = $affiliate->payouts()
                ->where('currency', $currency)
                ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
                ->lockForUpdate()
                ->first();

            if ($pending !== null) {
                return null;
            }

            $allocation = $this->fundedConversionAllocation(
                affiliate: $affiliate,
                availableMinor: $balance->available_minor,
                currency: $currency,
                lockForUpdate: true,
            );
            $conversionIds = $allocation['conversion_ids'];
            $amountMinor = $allocation['amount_minor'];

            if ($conversionIds === [] || $amountMinor < $threshold) {
                return null;
            }

            $sequence = $balance->payout_sequence + 1;
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
                (string) $balance->getKey(),
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

    /**
     * Allocate a deterministic FIFO prefix of fully funded conversions.
     *
     * @return array{conversion_ids:list<string>,amount_minor:int}
     */
    private function fundedConversionAllocation(
        Affiliate $affiliate,
        int $availableMinor,
        string $currency,
        bool $lockForUpdate = false,
    ): array {
        if ($availableMinor <= 0) {
            return ['conversion_ids' => [], 'amount_minor' => 0];
        }

        $query = $affiliate->conversions()
            ->where('status', ApprovedConversion::value())
            ->whereNull('affiliate_payout_id')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->select(['id', 'commission_minor', 'commission_currency']);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $conversionIds = [];
        $amountMinor = 0;

        // Fixed-size lazy batches keep memory bounded on high-volume
        // affiliates. Skipped rows (other currencies, zero commission) must
        // not consume a row limit, so limit(availableMinor) is wrong; and
        // lazyById would reorder the occurred_at,id FIFO, so ordered offset
        // chunks are used. No rows mutate during traversal (links update
        // after allocation), making offset pagination safe here.
        foreach ($query->lazy(1000) as $conversion) {
            if (mb_strtoupper((string) $conversion->commission_currency) !== $currency) {
                continue;
            }

            $commissionMinor = max(0, (int) $conversion->commission_minor);

            if ($commissionMinor === 0) {
                continue;
            }

            if ($amountMinor + $commissionMinor > $availableMinor) {
                break;
            }

            $conversionIds[] = (string) $conversion->id;
            $amountMinor += $commissionMinor;
        }

        return [
            'conversion_ids' => $conversionIds,
            'amount_minor' => $amountMinor,
        ];
    }
}

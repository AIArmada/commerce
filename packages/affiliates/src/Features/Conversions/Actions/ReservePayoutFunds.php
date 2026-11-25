<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\PayoutReservation;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use InvalidArgumentException;
use LogicException;

final readonly class ReservePayoutFunds implements PayoutReservation
{
    public function reserve(string $balanceId, string $affiliateId, string $payoutId, string $currency, int $amountMinor, int $sequence, array $conversionIds): void
    {
        $connection = (new AffiliateConversion)->getConnection();

        if ($connection !== (new AffiliateBalance)->getConnection() || $connection->transactionLevel() < 1) {
            throw new LogicException('Payout funds must be reserved inside the shared accounting transaction.');
        }

        if ($amountMinor <= 0 || $conversionIds === [] || count($conversionIds) !== count(array_unique($conversionIds))) {
            throw new InvalidArgumentException('Payout reservation requires unique funded conversions.');
        }

        $balance = AffiliateBalance::query()->whereKey($balanceId)
            ->where('affiliate_id', $affiliateId)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->firstOrFail();

        if ($balance->available_minor < $amountMinor || $sequence !== $balance->payout_sequence + 1) {
            throw new InvalidArgumentException('Payout reservation no longer matches the available balance.');
        }

        $linkedMinor = 0;

        foreach (array_chunk($conversionIds, 500) as $ids) {
            $query = AffiliateConversion::query()->forOwner()
                ->where('affiliate_id', $affiliateId)
                ->whereIn('id', $ids)
                ->whereRaw('UPPER(commission_currency) = ?', [$currency])
                ->where('status', ApprovedConversion::value())
                ->where('commission_minor', '>', 0);

            $linked = (clone $query)->whereNull('affiliate_payout_id')->update(['affiliate_payout_id' => $payoutId]);

            if ($linked !== count($ids)) {
                throw new InvalidArgumentException('A selected conversion is no longer available for payout.');
            }

            $linkedMinor += (int) $query->where('affiliate_payout_id', $payoutId)->sum('commission_minor');
        }

        if ($linkedMinor !== $amountMinor) {
            throw new InvalidArgumentException('Payout reservation does not match the linked commission amount.');
        }

        $balance->forceFill([
            'available_minor' => $balance->available_minor - $amountMinor,
            'payout_sequence' => $sequence,
        ])->save();
    }
}

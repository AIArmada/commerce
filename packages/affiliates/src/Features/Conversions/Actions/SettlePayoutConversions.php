<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSettlement;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\CancelledPayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PaidConversion;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class SettlePayoutConversions implements PayoutConversionSettlement
{
    public function complete(string $payoutId): void
    {
        $payout = $this->lockedPayout($payoutId);

        if (! $payout->status->equals(CompletedPayout::class)) {
            throw new LogicException('Conversions can only settle for a completed payout.');
        }

        $query = AffiliateConversion::query()->forOwner()->where('affiliate_payout_id', $payoutId);
        $expected = $query->count();
        $affected = (clone $query)->where('status', ApprovedConversion::value())->update([
            'status' => PaidConversion::value(),
            'paid_at' => CarbonImmutable::now(),
        ]);

        if ($affected !== $expected) {
            throw new PayoutCompletionBlockedException(sprintf(
                'Payout [%s] cannot complete: %d of %d linked conversions left Approved out of band.',
                $payoutId,
                $expected - $affected,
                $expected,
            ));
        }
    }

    public function release(string $payoutId): bool
    {
        $payout = $this->lockedPayout($payoutId);

        if (! $payout->status->equals(FailedPayout::class) && ! $payout->status->equals(CancelledPayout::class)) {
            throw new LogicException('Only failed or cancelled payouts can release funds.');
        }

        $query = AffiliateConversion::query()->forOwner()->where('affiliate_payout_id', $payoutId);
        $operation = $payout->operation()->forOwner()->lockForUpdate()->first();

        if ($payout->affiliate_payout_operation_id !== null && ! $operation instanceof AffiliatePayoutOperation) {
            throw new PayoutCompletionBlockedException('Payout reservation operation is missing.');
        }

        if ($operation?->funds_released_at !== null) {
            if ($query->exists()) {
                throw new PayoutCompletionBlockedException('Released payout still has linked conversions.');
            }

            return false;
        }

        $expected = $query->count();
        $approved = (clone $query)->where('status', ApprovedConversion::value());

        if ($approved->count() !== $expected) {
            throw new PayoutCompletionBlockedException('Payout release refused: a linked conversion is no longer approved.');
        }

        $reserved = $operation?->payout_sequence !== null;

        if ($reserved) {
            $currency = mb_strtoupper($operation->currency);
            $matching = (clone $approved)->where('affiliate_id', $operation->affiliate_id)
                ->whereRaw('UPPER(commission_currency) = ?', [$currency]);

            if ($operation->affiliate_payout_id !== $payoutId
                || $operation->amount_minor <= 0
                || $expected !== $payout->conversion_count
                || $matching->count() !== $expected
                || (int) $matching->sum('commission_minor') !== $operation->amount_minor) {
                throw new PayoutCompletionBlockedException('Payout release refused: linked conversions no longer match the reservation.');
            }

            $balance = AffiliateBalance::query()->where('affiliate_id', $operation->affiliate_id)
                ->where('currency', $currency)->lockForUpdate()->first();

            if (! $balance instanceof AffiliateBalance) {
                throw new PayoutCompletionBlockedException('Payout release refused: reserved balance is missing.');
            }

            $balance->increment('available_minor', $operation->amount_minor);
        }

        if ($approved->update(['affiliate_payout_id' => null]) !== $expected) {
            throw new PayoutCompletionBlockedException('Payout release refused: linked conversions changed during settlement.');
        }

        return $reserved;
    }

    private function lockedPayout(string $payoutId): AffiliatePayout
    {
        $connection = (new AffiliatePayout)->getConnection();

        if ($connection->transactionLevel() < 1
            || (new AffiliateConversion)->getConnection() !== $connection
            || (new AffiliateBalance)->getConnection() !== $connection
            || (new AffiliatePayoutOperation)->getConnection() !== $connection) {
            throw new LogicException('Payout settlement requires the shared payout transaction.');
        }

        return AffiliatePayout::query()->forOwner()->lockForUpdate()->findOrFail($payoutId);
    }
}

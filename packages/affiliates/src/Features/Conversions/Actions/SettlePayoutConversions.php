<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionSettlement;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAccountingReader;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAccountingRecord;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReservationReader;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\PaidConversion;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class SettlePayoutConversions implements PayoutConversionSettlement
{
    public function __construct(
        private PayoutReservationReader $reservations,
        private PayoutConversionIntegrity $integrity,
        private PayoutAccountingReader $payouts,
    ) {}

    public function complete(string $payoutId): void
    {
        $payout = $this->lockedPayout($payoutId);

        if ($payout->status !== PayoutStatus::Completed->value) {
            throw new LogicException('Conversions can only settle for a completed payout.');
        }

        $this->assertConversionOwners($payout);

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

        if ($payout->status !== PayoutStatus::Failed->value && $payout->status !== PayoutStatus::Cancelled->value) {
            throw new LogicException('Only failed or cancelled payouts can release funds.');
        }

        $this->assertConversionOwners($payout);

        $query = AffiliateConversion::query()->forOwner()->where('affiliate_payout_id', $payoutId);
        $operation = $payout->operationId === null ? null : $this->reservations->locked($payout->operationId);

        if ($payout->operationId !== null && $operation === null) {
            throw new PayoutCompletionBlockedException('Payout reservation operation is missing.');
        }

        if ($operation?->fundsReleased === true) {
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

        $reserved = $operation?->sequence !== null;

        if ($operation !== null && $operation->sequence !== null) {
            $currency = mb_strtoupper($operation->currency);
            $matching = (clone $approved)->where('affiliate_id', $operation->affiliateId)
                ->whereRaw('UPPER(commission_currency) = ?', [$currency]);

            if ($operation->payoutId !== $payoutId
                || $operation->amountMinor <= 0
                || $expected !== $payout->conversionCount
                || $matching->count() !== $expected
                || (int) $matching->sum('commission_minor') !== $operation->amountMinor) {
                throw new PayoutCompletionBlockedException('Payout release refused: linked conversions no longer match the reservation.');
            }

            $balance = AffiliateBalance::query()->where('affiliate_id', $operation->affiliateId)
                ->where('currency', $currency)->lockForUpdate()->first();

            if (! $balance instanceof AffiliateBalance) {
                throw new PayoutCompletionBlockedException('Payout release refused: reserved balance is missing.');
            }

            $balance->increment('available_minor', $operation->amountMinor);
        }

        if ($approved->update(['affiliate_payout_id' => null]) !== $expected) {
            throw new PayoutCompletionBlockedException('Payout release refused: linked conversions changed during settlement.');
        }

        return $reserved;
    }

    private function assertConversionOwners(PayoutAccountingRecord $payout): void
    {
        $summary = $this->integrity->summary(
            $payout->id,
            $payout->ownerType,
            $payout->ownerId,
            spl_object_id((new AffiliateConversion)->getConnection()),
        );

        if ($summary->foreignOwnerCount !== 0) {
            throw new PayoutCompletionBlockedException('Payout links conversions outside its owner scope.');
        }
    }

    private function lockedPayout(string $payoutId): PayoutAccountingRecord
    {
        $connection = (new AffiliateConversion)->getConnection();

        if ($connection->transactionLevel() < 1
            || (new AffiliateBalance)->getConnection() !== $connection) {
            throw new LogicException('Payout settlement requires the shared payout transaction.');
        }

        $this->reservations->assertSharedTransaction(spl_object_id($connection));

        return $this->payouts->locked($payoutId, spl_object_id($connection));
    }
}

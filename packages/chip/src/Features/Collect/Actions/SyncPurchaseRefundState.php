<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

use AIArmada\Chip\Contracts\PurchaseStatus;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Features\Collect\Api\RefundStateSynchronizer;
use AIArmada\Chip\Features\Collect\Models\Payment;
use AIArmada\Chip\Features\Collect\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Synchronize local purchase refund state from a CHIP refund payment payload.
 */
final readonly class SyncPurchaseRefundState implements RefundStateSynchronizer
{
    public function handle(PaymentData $refundPayment, ?string $purchaseId = null): void
    {
        if ($refundPayment->payment_type !== 'refund') {
            return;
        }

        $relatedId = $refundPayment->getRelatedPurchaseId();
        if ($purchaseId !== null && $relatedId !== null && $purchaseId !== $relatedId) {
            return;
        }

        $purchaseId ??= $relatedId;

        $paymentId = $refundPayment->getPaymentId();

        if ($purchaseId === null || $paymentId === null || $paymentId === '') {
            return;
        }

        $refundAmount = $refundPayment->getAmountInCents();
        if ($refundAmount <= 0) {
            return;
        }

        // Lock the row so concurrent refund webhooks cannot both read the
        // same cumulative total and undercount.
        DB::transaction(function () use ($purchaseId, $paymentId, $refundPayment, $refundAmount): void {
            $locked = Purchase::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($purchaseId)->lockForUpdate()->first();

            if (! $locked instanceof Purchase) {
                return;
            }

            $currency = $locked->currency;

            if ($currency !== null && $currency !== $refundPayment->getCurrency()) {
                throw new ChipValidationException('Refund currency does not match the purchase currency.');
            }

            $purchaseTotal = $this->resolvePurchaseTotal($locked);
            $existingRefundAmount = max(0, (int) ($locked->refund_amount_minor ?? 0));

            $persistedRefundAmount = (int) $locked->payments()
                ->where('payment_type', 'refund')
                ->sum('amount');

            $cumulativeRefundAmount = max($persistedRefundAmount, $existingRefundAmount);

            $payment = Payment::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($paymentId)->first();

            if ($payment instanceof Payment) {
                if ((string) $payment->purchase_id !== $purchaseId || $payment->payment_type !== 'refund' || (int) $payment->amount !== $refundAmount || $payment->currency !== $refundPayment->getCurrency()) {
                    throw new ChipValidationException('Refund payment identifier conflicts with an existing payment.');
                }
            } else {
                $this->persistRefund($locked, $refundPayment, $paymentId);
                $cumulativeRefundAmount += $refundAmount;
            }

            if ($cumulativeRefundAmount <= 0 || ($locked->status === PurchaseStatus::REFUNDED->value && $existingRefundAmount === $cumulativeRefundAmount)) {
                return;
            }

            $locked->forceFill([
                // CHIP uses refunded for both full and partial refunds. The
                // local refund amount fields preserve the distinction.
                'status' => PurchaseStatus::REFUNDED->value,
                'refund_amount_minor' => $cumulativeRefundAmount,
                'refundable_amount' => $purchaseTotal > 0 ? max(0, $purchaseTotal - $cumulativeRefundAmount) : 0,
                'refunded_at' => CarbonImmutable::now(),
            ])->save();
        });
    }

    private function resolvePurchaseTotal(Purchase $purchase): int
    {
        $total = Arr::get($purchase->purchase, 'total');

        return is_numeric($total) ? (int) $total : 0;
    }

    private function persistRefund(Purchase $purchase, PaymentData $refund, string $paymentId): void
    {
        $payment = new Payment;
        $payment->forceFill([
            'id' => $paymentId,
            'purchase_id' => $purchase->getKey(),
            'payment_type' => 'refund',
            'is_outgoing' => $refund->is_outgoing,
            'amount' => $refund->getAmountInCents(),
            'currency' => $refund->getCurrency(),
            'net_amount' => $refund->getNetAmountInCents(),
            'fee_amount' => $refund->getFeeAmountInCents(),
            'pending_amount' => $refund->getPendingAmountInCents(),
            'pending_unfreeze_on' => $refund->pending_unfreeze_on,
            'description' => $refund->description,
            'paid_on' => $refund->paid_on,
            'remote_paid_on' => $refund->remote_paid_on,
            'created_on' => $refund->created_on ?? time(),
            'updated_on' => $refund->updated_on ?? $refund->created_on ?? time(),
            'owner_type' => $purchase->owner_type,
            'owner_id' => $purchase->owner_id,
        ])->save();
    }
}

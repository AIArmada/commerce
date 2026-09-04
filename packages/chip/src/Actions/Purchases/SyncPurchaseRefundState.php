<?php

declare(strict_types=1);

namespace AIArmada\Chip\Actions\Purchases;

use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\Chip\Models\Purchase;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Synchronize local purchase refund state from a CHIP refund payment payload.
 */
final class SyncPurchaseRefundState
{
    use AsAction;

    public function handle(PaymentData $refundPayment, ?Purchase $purchase = null): ?Purchase
    {
        if ($refundPayment->payment_type !== 'refund') {
            return $purchase;
        }

        $purchase ??= $this->resolvePurchase($refundPayment);

        if ($purchase === null) {
            return null;
        }

        $refundAmount = $refundPayment->getAmountInCents();
        if ($refundAmount <= 0) {
            return $purchase;
        }

        $purchaseTotal = $this->resolvePurchaseTotal($purchase);
        $currentPaymentExists = $this->currentRefundPaymentExists($purchase, $refundPayment);
        $existingRefundAmount = max(0, (int) ($purchase->refund_amount_minor ?? 0));

        $persistedRefundAmount = (int) $purchase->payments()
            ->where('payment_type', 'refund')
            ->sum('amount');

        $cumulativeRefundAmount = max($persistedRefundAmount, $existingRefundAmount);

        if (! $currentPaymentExists) {
            $cumulativeRefundAmount += $refundAmount;
        }

        if ($cumulativeRefundAmount <= 0) {
            return $purchase;
        }

        $purchase->forceFill([
            // CHIP uses refunded for both full and partial refunds. The
            // local refund amount fields preserve the distinction.
            'status' => PurchaseStatus::REFUNDED->value,
            'refund_amount_minor' => $cumulativeRefundAmount,
            'refundable_amount' => $purchaseTotal > 0 ? max(0, $purchaseTotal - $cumulativeRefundAmount) : 0,
            'refunded_at' => now(),
        ])->save();

        return $purchase->refresh();
    }

    private function resolvePurchase(PaymentData $refundPayment): ?Purchase
    {
        $purchaseId = $refundPayment->getRelatedPurchaseId();

        if ($purchaseId === null) {
            return null;
        }

        return Purchase::query()->find($purchaseId);
    }

    private function resolvePurchaseTotal(Purchase $purchase): int
    {
        $total = Arr::get($purchase->purchase, 'total');

        return is_numeric($total) ? (int) $total : 0;
    }

    private function currentRefundPaymentExists(Purchase $purchase, PaymentData $refundPayment): bool
    {
        $paymentId = $refundPayment->getPaymentId();

        if ($paymentId === null || $paymentId === '') {
            return false;
        }

        return $purchase->payments()->whereKey($paymentId)->exists();
    }
}

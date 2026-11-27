<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Actions;

use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Enums\PurchaseStatus;
use AIArmada\Chip\Features\Collect\Api\RefundStateSynchronizer;
use AIArmada\Chip\Models\Purchase;
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

        if ($purchaseId === null) {
            return;
        }

        $refundAmount = $refundPayment->getAmountInCents();
        if ($refundAmount <= 0) {
            return;
        }

        // Lock the row so concurrent refund webhooks cannot both read the
        // same cumulative total and undercount.
        DB::transaction(function () use ($purchaseId, $refundPayment, $refundAmount): void {
            $locked = Purchase::query()->forOwner(OwnerContext::CURRENT, false)->whereKey($purchaseId)->lockForUpdate()->first();

            if (! $locked instanceof Purchase) {
                return;
            }

            $purchaseTotal = $this->resolvePurchaseTotal($locked);
            $existingRefundAmount = max(0, (int) ($locked->refund_amount_minor ?? 0));

            $persistedRefundAmount = (int) $locked->payments()
                ->where('payment_type', 'refund')
                ->sum('amount');

            $cumulativeRefundAmount = max($persistedRefundAmount, $existingRefundAmount);

            if (! $this->currentRefundPaymentExists($locked, $refundPayment)) {
                $cumulativeRefundAmount += $refundAmount;
            }

            if ($cumulativeRefundAmount <= 0) {
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

    private function currentRefundPaymentExists(Purchase $purchase, PaymentData $refundPayment): bool
    {
        $paymentId = $refundPayment->getPaymentId();

        if ($paymentId === null || $paymentId === '') {
            return false;
        }

        return $purchase->payments()->whereKey($paymentId)->exists();
    }
}

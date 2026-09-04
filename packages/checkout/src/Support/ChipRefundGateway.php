<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Facades\Chip;
use Throwable;

final readonly class ChipRefundGateway
{
    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        try {
            $refund = Chip::refundPurchase($paymentId, $amount);
            $response = method_exists($refund, 'toArray')
                ? $refund->toArray()
                : (array) $refund;
            $status = data_get($response, 'status');
            $status = is_string($status) ? mb_strtolower(mb_trim($status)) : null;
            $paymentStatus = match ($status) {
                'refunded' => PaymentStatus::Refunded,
                'partially_refunded' => PaymentStatus::PartiallyRefunded,
                'pending_refund', 'attempted_refund' => PaymentStatus::Processing,
                'failed', 'error', 'blocked' => PaymentStatus::Failed,
                // A provider response without a recognised status must never
                // be treated as a completed money movement.
                default => PaymentStatus::Processing,
            };

            // CHIP returns the original PurchaseData while a refund is still
            // pending. Its identifiers belong to the original purchase, not
            // to the refund operation.
            $transactionId = $refund instanceof PaymentData
                ? $this->refundTransactionId($response, $paymentId)
                : null;

            return new PaymentResult(
                status: $paymentStatus,
                paymentId: $paymentId,
                transactionId: $transactionId,
                amount: $amount,
                message: $paymentStatus === PaymentStatus::Processing
                    ? 'Refund is being processed by CHIP'
                    : ($paymentStatus === PaymentStatus::Failed ? 'CHIP could not process the refund' : 'Refund processed successfully'),
                gatewayResponse: $response,
                provider: 'chip',
            );
        } catch (Throwable $e) {
            return PaymentResult::failed("Refund failed: {$e->getMessage()}", [], $paymentId);
        }
    }

    /**
     * Extract a refund operation identifier without mistaking the original
     * purchase identifier for the refund identifier.
     *
     * @param  array<string, mixed>  $response
     */
    private function refundTransactionId(array $response, string $paymentId): ?string
    {
        $candidates = [
            data_get($response, 'refund_id'),
            data_get($response, 'transaction_id'),
            data_get($response, 'payment.id'),
            data_get($response, 'refund.id'),
            data_get($response, 'id'),
            data_get($response, 'reference_generated'),
            data_get($response, 'reference'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $candidate = mb_trim((string) $candidate);

            if ($candidate !== '' && $candidate !== $paymentId) {
                return $candidate;
            }
        }

        return null;
    }
}

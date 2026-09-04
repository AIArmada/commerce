<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Chip\Data\PaymentData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;
use Throwable;

final readonly class ChipRefundGateway
{
    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        try {
            $refund = Chip::refundPurchase($paymentId, $amount);
            $response = $refund->toArray();
            $paymentStatus = match (true) {
                $refund instanceof PaymentData && $refund->payment_type === 'refund' => PaymentStatus::Refunded,
                $refund instanceof PurchaseData && $refund->status === 'pending_refund' => PaymentStatus::Processing,
                $refund instanceof PurchaseData && $refund->status === 'refunded' => PaymentStatus::Refunded,
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
                    : 'Refund processed successfully',
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
        $candidate = $response['id'] ?? null;

        if (is_scalar($candidate)) {
            $candidate = mb_trim((string) $candidate);

            if ($candidate !== '' && $candidate !== $paymentId) {
                return $candidate;
            }
        }

        return null;
    }
}

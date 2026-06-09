<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Chip\Facades\Chip;
use Throwable;

final readonly class ChipRefundGateway
{
    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        try {
            $refund = Chip::refundPurchase($paymentId, $amount);

            return new PaymentResult(
                status: PaymentStatus::Refunded,
                paymentId: $paymentId,
                amount: $amount,
                message: 'Refund processed successfully',
                gatewayResponse: (array) $refund,
            );
        } catch (Throwable $e) {
            return PaymentResult::failed("Refund failed: {$e->getMessage()}", [], $paymentId);
        }
    }
}

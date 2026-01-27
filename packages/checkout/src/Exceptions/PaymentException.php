<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Exceptions;

final class PaymentException extends CheckoutException
{
    public static function paymentFailed(string $reason, ?string $paymentId = null): self
    {
        return new self(
            "Payment failed: {$reason}",
            ['payment_id' => $paymentId, 'reason' => $reason],
        );
    }

    public static function retryLimitExceeded(int $attempts, int $limit): self
    {
        return new self(
            "Payment retry limit exceeded ({$attempts}/{$limit})",
            ['attempts' => $attempts, 'limit' => $limit],
        );
    }

    public static function invalidPaymentState(string $currentState, string $expectedState): self
    {
        return new self(
            "Invalid payment state: expected '{$expectedState}', got '{$currentState}'",
            ['current_state' => $currentState, 'expected_state' => $expectedState],
        );
    }

    public static function refundFailed(string $paymentId, string $reason): self
    {
        return new self(
            "Refund failed for payment '{$paymentId}': {$reason}",
            ['payment_id' => $paymentId, 'reason' => $reason],
        );
    }
}

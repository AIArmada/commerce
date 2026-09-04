<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Checkout\Data\PaymentResult;

/**
 * Optional extension for processors that wrap multiple concrete providers.
 *
 * The checkout gateway remains stable in persisted order data while the
 * concrete provider used for a payment can still be selected for follow-up
 * operations such as refunds and status checks.
 */
interface ProviderAwarePaymentProcessorInterface extends PaymentProcessorInterface
{
    public function refundForProvider(
        string $provider,
        string $paymentId,
        int $amount,
        ?string $reason = null,
    ): PaymentResult;

    public function checkStatusForProvider(string $provider, string $paymentId): PaymentResult;
}

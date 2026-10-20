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
interface ProviderAwarePaymentProcessorInterface extends PaymentCompensationInterface, PaymentProcessorInterface
{
    /**
     * Resolve the concrete provider a request carrying the given selected
     * provider hint will actually be submitted through.
     *
     * Called before an attempt identity is frozen so an unresolved default
     * is never persisted into a claim; replaying the attempt then reuses the
     * frozen provider instead of re-reading a mutable default.
     */
    public function resolveProvider(?string $selectedProvider = null): ?string;

    public function refundForProvider(
        string $provider,
        string $paymentId,
        int $amount,
        ?string $reason = null,
    ): PaymentResult;

    public function voidPaymentForProvider(
        string $provider,
        string $paymentId,
        ?string $reason = null,
    ): PaymentResult;

    public function checkStatusForProvider(string $provider, string $paymentId): PaymentResult;
}

<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Models\CheckoutSession;

interface PaymentProcessorInterface
{
    /**
     * Get the processor identifier.
     */
    public function getIdentifier(): string;

    /**
     * Get the display name.
     */
    public function getName(): string;

    /**
     * Check if this processor is available for the given session.
     */
    public function isAvailable(CheckoutSession $session): bool;

    /**
     * Create a payment for the checkout session.
     */
    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult;

    /**
     * Handle payment callback/webhook.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): PaymentResult;

    /**
     * Get the redirect URL for payment.
     */
    public function getRedirectUrl(CheckoutSession $session): ?string;

    /**
     * Refund a payment.
     */
    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult;

    /**
     * Check payment status.
     */
    public function checkStatus(string $paymentId): PaymentResult;
}

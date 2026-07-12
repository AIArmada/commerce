<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Models\CheckoutSession;

interface CheckoutServiceInterface
{
    /**
     * Start a new checkout session from a cart.
     */
    public function startCheckout(string $cartId, ?string $customerId = null): CheckoutSession;

    /**
     * Resume an existing checkout session.
     */
    public function resumeCheckout(string $sessionId): CheckoutSession;

    /**
     * Process the checkout through all configured steps.
     */
    public function processCheckout(CheckoutSession $session): CheckoutResult;

    /**
     * Retry payment for a failed checkout session.
     */
    public function retryPayment(CheckoutSession $session): CheckoutResult;

    /**
     * Cancel an in-progress checkout session.
     */
    public function cancelCheckout(CheckoutSession $session): CheckoutSession;

    /**
     * Handle payment callback/webhook after gateway redirect/notification.
     *
     * @param  array<string, mixed>  $payload  Optional webhook payload
     */
    public function handlePaymentCallback(
        CheckoutSession $session,
        string $callbackType,
        array $payload = [],
    ): CheckoutResult;
}

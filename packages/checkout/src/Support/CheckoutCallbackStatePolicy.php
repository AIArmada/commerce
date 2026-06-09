<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Support;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;

final readonly class CheckoutCallbackStatePolicy
{
    public function canHandleCallback(CheckoutSession $session, string $callbackType): bool
    {
        $isInPaymentState = $session->status instanceof AwaitingPayment
            || $session->status instanceof PaymentProcessing
            || $session->status instanceof Processing;

        if ($callbackType === 'success') {
            return $isInPaymentState;
        }

        if ($callbackType === 'failure' || $callbackType === 'cancel') {
            return $isInPaymentState || $session->status instanceof Pending;
        }

        return false;
    }

    public function isCallbackIdempotent(CheckoutSession $session): bool
    {
        return $session->status instanceof Completed;
    }
}

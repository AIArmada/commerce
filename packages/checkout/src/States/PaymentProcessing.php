<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

/**
 * Payment is being processed by the gateway.
 */
final class PaymentProcessing extends CheckoutState
{
    public static string $name = 'payment_processing';

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrow-path';
    }

    public function label(): string
    {
        return __('checkout::states.payment_processing');
    }
}

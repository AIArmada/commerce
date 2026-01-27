<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

/**
 * Waiting for payment gateway response (redirect flow).
 */
final class AwaitingPayment extends CheckoutState
{
    public static string $name = 'awaiting_payment';

    public function color(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'heroicon-o-credit-card';
    }

    public function label(): string
    {
        return __('checkout::states.awaiting_payment');
    }

    public function canCancel(): bool
    {
        return true;
    }
}

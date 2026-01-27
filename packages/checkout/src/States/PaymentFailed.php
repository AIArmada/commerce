<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

/**
 * Payment attempt failed - can retry.
 */
final class PaymentFailed extends CheckoutState
{
    public static string $name = 'payment_failed';

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public function label(): string
    {
        return __('checkout::states.payment_failed');
    }

    public function canCancel(): bool
    {
        return true;
    }

    public function canRetryPayment(): bool
    {
        return true;
    }

    public function canModify(): bool
    {
        return true;
    }
}

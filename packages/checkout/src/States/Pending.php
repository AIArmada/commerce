<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

/**
 * Initial state when checkout session is created.
 */
final class Pending extends CheckoutState
{
    public static string $name = 'pending';

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'heroicon-o-clock';
    }

    public function label(): string
    {
        return __('checkout::states.pending');
    }

    public function canCancel(): bool
    {
        return true;
    }

    public function canModify(): bool
    {
        return true;
    }
}

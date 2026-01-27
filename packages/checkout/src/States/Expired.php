<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

/**
 * Checkout session has expired - terminal state.
 */
final class Expired extends CheckoutState
{
    public static string $name = 'expired';

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
        return __('checkout::states.expired');
    }

    public function isTerminal(): bool
    {
        return true;
    }
}

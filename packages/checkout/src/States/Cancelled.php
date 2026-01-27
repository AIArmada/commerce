<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

/**
 * Checkout was cancelled by user or system - terminal state.
 */
final class Cancelled extends CheckoutState
{
    public static string $name = 'cancelled';

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): string
    {
        return 'heroicon-o-x-circle';
    }

    public function label(): string
    {
        return __('checkout::states.cancelled');
    }

    public function isTerminal(): bool
    {
        return true;
    }
}

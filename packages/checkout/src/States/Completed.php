<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

/**
 * Checkout completed successfully - terminal state.
 */
final class Completed extends CheckoutState
{
    public static string $name = 'completed';

    public function color(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'heroicon-o-check-circle';
    }

    public function label(): string
    {
        return __('checkout::states.completed');
    }

    public function isTerminal(): bool
    {
        return true;
    }
}

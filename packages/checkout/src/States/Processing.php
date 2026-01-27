<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

/**
 * Checkout is being processed (steps executing).
 */
final class Processing extends CheckoutState
{
    public static string $name = 'processing';

    public function color(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'heroicon-o-cog';
    }

    public function label(): string
    {
        return __('checkout::states.processing');
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

<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum PricingMode: string
{
    use HasLabelOptions;

    case Paid = 'paid';
    case Free = 'free';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Free => 'Free',
            self::Mixed => 'Mixed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'danger',
            self::Free => 'success',
            self::Mixed => 'warning',
        };
    }

    public function isFreeOnly(): bool
    {
        return $this === self::Free;
    }
}

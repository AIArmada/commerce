<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum CommissionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Fixed => 'Fixed Amount',
        };
    }
}

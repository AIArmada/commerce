<?php

declare(strict_types=1);

namespace AIArmada\Seating\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum SeatStatus: string
{
    use HasLabelOptions;

    case Available = 'available';
    case Held = 'held';
    case Sold = 'sold';
    case Picked = 'picked';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Held => 'Held',
            self::Sold => 'Sold',
            self::Picked => 'Picked',
            self::Blocked => 'Blocked',
        };
    }
}

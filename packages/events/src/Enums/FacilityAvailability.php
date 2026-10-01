<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum FacilityAvailability: string
{
    use HasLabelOptions;

    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Unavailable => 'Unavailable',
        };
    }
}

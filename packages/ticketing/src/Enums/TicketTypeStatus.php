<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum TicketTypeStatus: string
{
    use HasLabelOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case SoldOut = 'sold_out';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::SoldOut => 'Sold Out',
            self::Ended => 'Ended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Paused => 'warning',
            self::SoldOut, self::Cancelled => 'danger',
            self::Ended => 'info',
        };
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum TicketTypeVisibility: string
{
    use HasLabelOptions;

    case Public = 'public';
    case Private = 'private';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::Hidden => 'Hidden',
        };
    }
}

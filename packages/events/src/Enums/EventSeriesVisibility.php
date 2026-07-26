<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum EventSeriesVisibility: string
{
    use HasLabelOptions;

    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Unlisted => 'Unlisted',
            self::Private => 'Private',
            self::Hidden => 'Hidden',
        };
    }
}

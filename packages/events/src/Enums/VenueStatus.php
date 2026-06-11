<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum VenueStatus: string
{
    use HasLabelOptions;

    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }
}

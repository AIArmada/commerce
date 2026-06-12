<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum VenueStatus: string
{
    use HasLabelOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Closed = 'closed';
    case UnderMaintenance = 'under_maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Closed => 'Closed',
            self::UnderMaintenance => 'Under Maintenance',
        };
    }
}

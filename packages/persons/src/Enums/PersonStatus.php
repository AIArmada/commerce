<?php

declare(strict_types=1);

namespace AIArmada\Persons\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum PersonStatus: string
{
    use HasLabelOptions;

    case Active = 'active';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Persons\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum TitleUsagePosition: string
{
    use HasLabelOptions;

    case BeforeName = 'before_name';
    case AfterName = 'after_name';

    public function label(): string
    {
        return match ($this) {
            self::BeforeName => 'Before Name',
            self::AfterName => 'After Name',
        };
    }
}

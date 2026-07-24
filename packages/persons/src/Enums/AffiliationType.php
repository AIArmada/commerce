<?php

declare(strict_types=1);

namespace AIArmada\Persons\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum AffiliationType: string
{
    use HasLabelOptions;

    case Member = 'member';
    case Employee = 'employee';
    case Advisor = 'advisor';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Employee => 'Employee',
            self::Advisor => 'Advisor',
            self::Partner => 'Partner',
        };
    }
}

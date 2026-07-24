<?php

declare(strict_types=1);

namespace AIArmada\Persons\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum AssignmentStatus: string
{
    use HasLabelOptions;

    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired',
        };
    }
}

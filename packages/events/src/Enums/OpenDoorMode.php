<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum OpenDoorMode: string
{
    use HasLabelOptions;

    case Block = 'block';
    case WalkIn = 'walk_in';
    case Headcount = 'headcount';

    public function label(): string
    {
        return match ($this) {
            self::Block => 'Block Registration',
            self::WalkIn => 'Admin Walk-in Recording',
            self::Headcount => 'Headcount Logging',
        };
    }

    public function allowsRegistration(): bool
    {
        return $this === self::WalkIn || $this === self::Headcount;
    }

    public function isBlocked(): bool
    {
        return $this === self::Block;
    }
}

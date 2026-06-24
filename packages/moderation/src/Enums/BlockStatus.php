<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Enums;

enum BlockStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Lifted = 'lifted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Lifted => 'Lifted',
        };
    }
}

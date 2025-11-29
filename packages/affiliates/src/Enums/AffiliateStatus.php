<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum AffiliateStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending Approval',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Disabled => 'Disabled',
        };
    }
}

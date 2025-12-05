<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

enum FraudSignalStatus: string
{
    case Detected = 'detected';
    case Reviewed = 'reviewed';
    case Dismissed = 'dismissed';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Detected => 'Detected',
            self::Reviewed => 'Reviewed',
            self::Dismissed => 'Dismissed',
            self::Confirmed => 'Confirmed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Detected => 'warning',
            self::Reviewed => 'info',
            self::Dismissed => 'gray',
            self::Confirmed => 'danger',
        };
    }
}

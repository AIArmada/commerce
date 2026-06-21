<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum SuppressionReason: string
{
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Unsubscribed = 'unsubscribed';
    case Manual = 'manual';
    case Legal = 'legal';
    case Expired = 'expired';
    case Policy = 'policy';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
            self::Unsubscribed => 'Unsubscribed',
            self::Manual => 'Manual',
            self::Legal => 'Legal',
            self::Expired => 'Expired',
            self::Policy => 'Policy',
            self::System => 'System',
        };
    }
}

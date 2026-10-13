<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardComponent: string
{
    case Recognition = 'recognition';
    case Points = 'points';
    case Badge = 'badge';
    case Cash = 'cash';
    case Benefit = 'benefit';

    public function requiresMoneyOrBenefitFulfillment(): bool
    {
        return $this === self::Cash || $this === self::Benefit;
    }
}

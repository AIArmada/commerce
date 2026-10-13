<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardSourceFraudStatus: string
{
    case Clear = 'clear';
    case Suspect = 'suspect';
    case Confirmed = 'confirmed';
}

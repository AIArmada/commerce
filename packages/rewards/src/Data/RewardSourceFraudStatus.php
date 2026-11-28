<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

enum RewardSourceFraudStatus: string
{
    case Clear = 'clear';
    case Suspect = 'suspect';
    case Confirmed = 'confirmed';
}

<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardMilestoneStatus: string
{
    case Open = 'open';
    case Achieved = 'achieved';
    case Invalidated = 'invalidated';
}

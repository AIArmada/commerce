<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardAdjustmentStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}

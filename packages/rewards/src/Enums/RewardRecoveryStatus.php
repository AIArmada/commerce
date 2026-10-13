<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardRecoveryStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
    case WrittenOff = 'written_off';
}

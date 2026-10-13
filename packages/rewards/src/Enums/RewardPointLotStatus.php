<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardPointLotStatus: string
{
    case Open = 'open';
    case Depleted = 'depleted';
    case Expired = 'expired';
}

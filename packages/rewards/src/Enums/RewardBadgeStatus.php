<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardBadgeStatus: string
{
    case Granted = 'granted';
    case Revoked = 'revoked';
}

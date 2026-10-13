<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardBudgetMovementKind: string
{
    case Reserve = 'reserve';
    case Release = 'release';
    case Spend = 'spend';
    case Recover = 'recover';
}

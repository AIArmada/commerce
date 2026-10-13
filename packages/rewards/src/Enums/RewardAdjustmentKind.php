<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardAdjustmentKind: string
{
    case Override = 'override';
    case Adjustment = 'adjustment';
    case Dispute = 'dispute';
}

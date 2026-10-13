<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardRecoveryKind: string
{
    case Receivable = 'receivable';
    case Adjustment = 'adjustment';
    case Dispute = 'dispute';
}

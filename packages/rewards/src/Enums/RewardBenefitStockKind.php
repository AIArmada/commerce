<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardBenefitStockKind: string
{
    case Reserve = 'reserve';
    case Release = 'release';
    case Consume = 'consume';
    case Restock = 'restock';
}

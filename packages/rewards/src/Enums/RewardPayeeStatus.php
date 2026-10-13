<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Enums;

enum RewardPayeeStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}

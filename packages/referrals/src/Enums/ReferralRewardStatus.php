<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Enums;

enum ReferralRewardStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Reversed = 'reversed';
    case Failed = 'failed';
}

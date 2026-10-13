<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Enums;

enum ReferralRewardSide: string
{
    case Referrer = 'referrer';
    case Referee = 'referee';
}

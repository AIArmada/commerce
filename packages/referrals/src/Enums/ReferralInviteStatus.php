<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Enums;

enum ReferralInviteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Qualified = 'qualified';
    case Rewarded = 'rewarded';
    case Expired = 'expired';
    case Revoked = 'revoked';
}

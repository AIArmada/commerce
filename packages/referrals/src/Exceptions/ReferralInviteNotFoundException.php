<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Exceptions;

final class ReferralInviteNotFoundException extends ReferralException
{
    public static function invalid(): self
    {
        return new self('The referral invite could not be found or claimed.');
    }
}

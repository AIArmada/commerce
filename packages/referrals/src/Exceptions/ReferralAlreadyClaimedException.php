<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Exceptions;

final class ReferralAlreadyClaimedException extends ReferralException
{
    public static function forInvitee(): self
    {
        return new self('A live referral invite already claims this invitee.');
    }
}

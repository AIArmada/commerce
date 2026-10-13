<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Data;

use AIArmada\Referrals\Models\ReferralInvite;

final readonly class ReferralInviteCreationResult
{
    public function __construct(
        public ReferralInvite $invite,
        public ?string $token,
    ) {}
}

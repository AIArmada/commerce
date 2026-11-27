<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions\Results;

use AIArmada\Referrals\Models\ReferralInvite;

final readonly class ReferralInviteCreationResult
{
    public function __construct(
        public ReferralInvite $invite,
        public ?string $token,
    ) {}
}

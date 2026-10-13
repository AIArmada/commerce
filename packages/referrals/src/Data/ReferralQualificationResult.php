<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Data;

use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralReward;

final readonly class ReferralQualificationResult
{
    /**
     * @param  list<ReferralReward>  $rewards
     */
    public function __construct(
        public ReferralInvite $invite,
        public array $rewards,
    ) {}
}

<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Events;

use AIArmada\Referrals\Models\ReferralReward;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReferralRewardFulfilled implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly ReferralReward $reward) {}
}

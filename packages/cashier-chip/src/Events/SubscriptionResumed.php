<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CashierChip\Subscription\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionResumed
{
    use Dispatchable;
    use SerializesModels;

    public Subscription $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}

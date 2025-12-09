<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CashierChip\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The subscription instance.
     */
    public Subscription $subscription;

    /**
     * The failure reason.
     */
    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, string $reason = '')
    {
        $this->subscription = $subscription;
        $this->reason = $reason;
    }
}

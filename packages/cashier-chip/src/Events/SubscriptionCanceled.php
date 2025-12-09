<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CashierChip\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCanceled
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The subscription instance.
     */
    public Subscription $subscription;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}

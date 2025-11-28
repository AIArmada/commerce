<?php

namespace AIArmada\CashierChip\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use AIArmada\CashierChip\Subscription;

class SubscriptionCreated
{
    use Dispatchable, SerializesModels;

    /**
     * The subscription instance.
     *
     * @var \AIArmada\CashierChip\Subscription
     */
    public Subscription $subscription;

    /**
     * Create a new event instance.
     *
     * @param  \AIArmada\CashierChip\Subscription  $subscription
     * @return void
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}

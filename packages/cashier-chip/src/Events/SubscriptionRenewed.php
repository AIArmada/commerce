<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use AIArmada\CashierChip\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewed
{
    use Dispatchable, SerializesModels;

    /**
     * The subscription instance.
     */
    public Subscription $subscription;

    /**
     * The payment from the renewal.
     */
    public mixed $payment;

    /**
     * Create a new event instance.
     */
    public function __construct(Subscription $subscription, mixed $payment = null)
    {
        $this->subscription = $subscription;
        $this->payment = $payment;
    }
}

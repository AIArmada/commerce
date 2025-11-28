<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Events;

use AIArmada\Cashier\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base event for subscription-related events with gateway support.
 */
abstract class SubscriptionEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly mixed $billable = null,
    ) {}

    /**
     * Get the subscription instance.
     */
    public function subscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * Get the gateway name from the subscription.
     */
    public function gateway(): string
    {
        return $this->subscription->gateway;
    }

    /**
     * Get the billable model.
     */
    public function billable(): mixed
    {
        return $this->billable ?? $this->subscription->owner;
    }
}

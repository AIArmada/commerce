<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Events;

use AIArmada\Cashier\Contracts\SubscriptionContract;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base event for subscription-related events with gateway support.
 *
 * This event works with subscriptions from any underlying package
 * (Laravel Cashier for Stripe, CashierChip, etc.) through the
 * SubscriptionContract interface.
 */
abstract class SubscriptionEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly SubscriptionContract $subscription,
        public readonly mixed $billable = null,
    ) {}

    /**
     * Get the subscription instance.
     */
    final public function subscription(): SubscriptionContract
    {
        return $this->subscription;
    }

    /**
     * Get the gateway name from the subscription.
     */
    final public function gateway(): string
    {
        return $this->subscription->gateway();
    }

    /**
     * Get the billable model.
     */
    final public function billable(): mixed
    {
        return $this->billable ?? $this->subscription->owner();
    }
}

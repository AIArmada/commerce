<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Events;

use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Support\SnapshotSubscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base event for subscription-related events with gateway support.
 *
 * This event works with subscriptions from any underlying package
 * (Laravel Cashier for Stripe, CashierChip, etc.) through the
 * SubscriptionContract interface.
 *
 * Live subscriptions wrap gateway SDK objects or models with loaded
 * relations, so only a scalar snapshot crosses the queue boundary.
 * Queued listeners must re-resolve the live subscription when they
 * need gateway operations or mutations.
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
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'subscription' => SnapshotSubscription::capture($this->subscription),
            'billable' => $this->getSerializedPropertyValue($this->billable ?? $this->subscription->owner()),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function __unserialize(array $values): void
    {
        $this->subscription = SnapshotSubscription::fromSnapshot((array) ($values['subscription'] ?? []));
        $this->billable = $this->getRestoredPropertyValue($values['billable'] ?? null);
    }

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

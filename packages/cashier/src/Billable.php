<?php

declare(strict_types=1);

namespace AIArmada\Cashier;

use AIArmada\Cashier\Concerns\ManagesGateway;
use AIArmada\Cashier\Models\Subscription;

/**
 * Unified Billable trait for multi-gateway payment support.
 *
 * This trait provides a unified interface for interacting with multiple
 * payment gateways (Stripe, CHIP, etc.) through a single API.
 *
 * Add this trait to your User model (or any billable model):
 *
 * ```php
 * use AIArmada\Cashier\Billable;
 *
 * class User extends Authenticatable
 * {
 *     use Billable;
 * }
 * ```
 *
 * Then you can use the unified API:
 *
 * ```php
 * // Use default gateway
 * $user->newGatewaySubscription('default', 'price_xxx')->create();
 *
 * // Use specific gateway
 * $user->gateway('chip')->subscription($user, 'default', 'price_xxx')->create();
 * ```
 */
trait Billable
{
    use ManagesGateway;

    /**
     * Get all subscriptions for the billable.
     *
     * This returns the local Eloquent relationship for subscriptions.
     * For gateway-specific subscriptions, use gatewaySubscriptions().
     */
    public function subscriptions()
    {
        return $this->morphMany(
            Cashier::$subscriptionModel ?? Subscription::class,
            'billable'
        )->orderBy('created_at', 'desc');
    }

    /**
     * Get a subscription by type.
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions->first(fn ($sub) => $sub->type === $type);
    }

    /**
     * Determine if the billable is subscribed to a given type.
     */
    public function subscribed(string $type = 'default', ?string $price = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $price ? $subscription->hasPrice($price) : true;
    }

    /**
     * Check if on trial.
     */
    public function onTrial(string $type = 'default', ?string $price = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription) {
            return false;
        }

        return $subscription->onTrial();
    }

    /**
     * Check if on a generic trial (on the billable, not subscription).
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }
}

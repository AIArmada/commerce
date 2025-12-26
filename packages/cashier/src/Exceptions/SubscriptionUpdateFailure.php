<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

/**
 * Exception thrown when a subscription update fails.
 */
final class SubscriptionUpdateFailure extends CashierException
{
    /**
     * Create exception for incomplete subscription.
     */
    public static function incompleteSubscription(string $subscriptionType): static
    {
        return new static(
            "The subscription [{$subscriptionType}] has an incomplete payment. Please complete the payment before performing this action."
        );
    }

    /**
     * Create exception for canceled subscription.
     */
    public static function subscriptionCanceled(): static
    {
        return new static('Cannot update a canceled subscription.');
    }

    /**
     * Create exception for duplicate subscription.
     */
    public static function duplicateSubscription(string $type): static
    {
        return new static("A subscription with type [{$type}] already exists.");
    }
}

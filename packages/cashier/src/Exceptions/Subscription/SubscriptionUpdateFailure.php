<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions\Subscription;

/** @phpstan-consistent-constructor */
class SubscriptionUpdateFailure extends SubscriptionException
{
    public static function incompleteSubscription(string $subscriptionType): static
    {
        return new static(
            "The subscription [{$subscriptionType}] has an incomplete payment. Please complete the payment before performing this action."
        );
    }

    public static function subscriptionCanceled(): static
    {
        return new static('Cannot update a canceled subscription.');
    }

    public static function duplicateSubscription(string $type): static
    {
        return new static("A subscription with type [{$type}] already exists.");
    }
}

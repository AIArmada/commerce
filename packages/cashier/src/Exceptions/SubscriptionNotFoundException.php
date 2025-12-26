<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

/**
 * Exception thrown when a subscription is not found.
 */
final class SubscriptionNotFoundException extends CashierException
{
    /**
     * Create a new subscription not found exception.
     */
    public static function create(string $type): static
    {
        return new static("Subscription [{$type}] not found.");
    }

    /**
     * Create exception for gateway subscription not found.
     */
    public static function onGateway(string $gateway, string $subscriptionId): static
    {
        $exception = new static("Subscription [{$subscriptionId}] not found on gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }
}

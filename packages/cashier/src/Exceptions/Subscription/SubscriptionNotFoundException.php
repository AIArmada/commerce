<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions\Subscription;

/** @phpstan-consistent-constructor */
class SubscriptionNotFoundException extends SubscriptionException
{
    public static function create(string $type): static
    {
        return new static("Subscription [{$type}] not found.");
    }

    public static function onGateway(string $gateway, string $subscriptionId): static
    {
        $exception = new static("Subscription [{$subscriptionId}] not found on gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }
}

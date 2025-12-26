<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

/**
 * Exception thrown when a customer is not found or cannot be created.
 */
final class CustomerNotFoundException extends CashierException
{
    /**
     * Create a new customer not found exception.
     */
    public static function create(string $gateway, string $identifier): static
    {
        $exception = new static("Customer [{$identifier}] not found on gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }

    /**
     * Create exception for billable without customer.
     */
    public static function notCreated(string $gateway): static
    {
        $exception = new static("Customer has not been created on gateway [{$gateway}].");
        $exception->setGateway($gateway);

        return $exception;
    }
}

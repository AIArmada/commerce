<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

/**
 * Exception thrown when an invalid gateway is specified.
 */
final class InvalidGatewayException extends CashierException
{
    /**
     * Create a new invalid gateway exception.
     */
    public static function create(string $gateway): static
    {
        return new static("Gateway [{$gateway}] is not configured properly.");
    }

    /**
     * Create exception for missing configuration.
     */
    public static function missingConfig(string $gateway, string $key): static
    {
        return new static("Gateway [{$gateway}] is missing required configuration key [{$key}].");
    }
}

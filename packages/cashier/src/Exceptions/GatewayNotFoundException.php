<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when a gateway is not found.
 */
final class GatewayNotFoundException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Gateway not found.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for a missing gateway.
     */
    public static function forGateway(string $gateway): static
    {
        return new static("Gateway [{$gateway}] not found. Make sure the gateway is configured in config/cashier.php.");
    }

    /**
     * Create an exception for a missing driver.
     */
    public static function forDriver(string $gateway): static
    {
        return new static("Gateway driver [{$gateway}] not found. Make sure the required package is installed.");
    }
}

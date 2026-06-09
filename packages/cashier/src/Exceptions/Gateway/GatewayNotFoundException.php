<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions\Gateway;

use Throwable;

/** @phpstan-consistent-constructor */
class GatewayNotFoundException extends GatewayException
{
    public function __construct(string $message = 'Gateway not found.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function forGateway(string $gateway): static
    {
        return new static("Gateway [{$gateway}] not found. Make sure the gateway is configured in config/cashier.php.");
    }

    public static function forDriver(string $gateway): static
    {
        return new static("Gateway driver [{$gateway}] not found. Make sure the required package is installed.");
    }
}

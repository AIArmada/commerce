<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions\Gateway;

/** @phpstan-consistent-constructor */
class InvalidGatewayException extends GatewayException
{
    public static function create(string $gateway): static
    {
        return new static("Gateway [{$gateway}] is not configured properly.");
    }

    public static function missingConfig(string $gateway, string $key): static
    {
        return new static("Gateway [{$gateway}] is missing required configuration key [{$key}].");
    }
}

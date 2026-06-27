<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

use Throwable;

final class GatewayRetrievalException extends CashierException
{
    public static function create(string $gateway, string $resource, string $identifier, Throwable $previous): self
    {
        $exception = new self(
            sprintf('Failed to retrieve %s [%s] from gateway [%s].', $resource, $identifier, $gateway),
            previous: $previous,
        );
        $exception->setGateway($gateway);

        return $exception;
    }
}

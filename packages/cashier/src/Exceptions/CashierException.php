<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

use Exception;

/**
 * Base exception for all Cashier exceptions.
 */
class CashierException extends Exception
{
    /**
     * The gateway that threw the exception.
     */
    protected ?string $gateway = null;

    /**
     * Set the gateway that threw the exception.
     */
    public function setGateway(string $gateway): static
    {
        $this->gateway = $gateway;

        return $this;
    }

    /**
     * Get the gateway that threw the exception.
     */
    public function gateway(): ?string
    {
        return $this->gateway;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Exceptions;

use Exception;

class CheckoutException extends Exception
{
    /**
     * Create a new checkout exception.
     */
    public static function make(string $message): self
    {
        return new self($message);
    }
}

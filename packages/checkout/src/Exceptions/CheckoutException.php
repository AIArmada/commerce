<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Exceptions;

use Exception;
use Throwable;

class CheckoutException extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function make(string $message, array $context = []): self
    {
        return new self($message, $context);
    }
}

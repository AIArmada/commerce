<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Exceptions;

use Exception;
use Throwable;

/**
 * Exception thrown when a gift card is invalid or cannot be used.
 */
class InvalidGiftCardException extends Exception
{
    public function __construct(string $message = 'Invalid gift card', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

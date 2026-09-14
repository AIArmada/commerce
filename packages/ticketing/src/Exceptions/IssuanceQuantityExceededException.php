<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Exceptions;

use RuntimeException;
use Throwable;

class IssuanceQuantityExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $maxQuantity,
        public readonly int $actualQuantity,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : "Pass issuance quantity {$actualQuantity} exceeds the maximum of {$maxQuantity}.", $code, $previous);
    }
}

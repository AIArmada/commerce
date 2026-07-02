<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Exceptions;

use RuntimeException;
use Throwable;

class BulkTransferSizeExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $maxSize,
        public readonly int $actualSize,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : "Bulk transfer size {$actualSize} exceeds the maximum of {$maxSize}.", $code, $previous);
    }
}

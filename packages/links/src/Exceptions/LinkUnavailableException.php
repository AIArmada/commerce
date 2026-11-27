<?php

declare(strict_types=1);

namespace AIArmada\Links\Exceptions;

use RuntimeException;

final class LinkUnavailableException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Link is no longer available: ' . $reason);
    }
}

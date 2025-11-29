<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Exceptions;

use RuntimeException;

final class AffiliateNotFoundException extends RuntimeException
{
    public static function withCode(string $code): self
    {
        return new self(sprintf("Affiliate '%s' was not found or is inactive.", $code));
    }
}

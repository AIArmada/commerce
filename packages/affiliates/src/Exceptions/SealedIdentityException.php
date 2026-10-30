<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Exceptions;

use RuntimeException;

final class SealedIdentityException extends RuntimeException
{
    public static function forAffiliate(string $affiliateId): self
    {
        return new self(sprintf("Affiliate '%s' has a sealed identity and cannot accept new claims.", $affiliateId));
    }
}

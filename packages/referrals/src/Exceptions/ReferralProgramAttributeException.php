<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Exceptions;

use InvalidArgumentException;
use Throwable;

/**
 * A referral program attribute failed normalization.
 *
 * Extends InvalidArgumentException so the documented core contract — and
 * every caller that catches InvalidArgumentException — is unchanged. The
 * attribute name is carried alongside the message so a presentation adapter
 * can attach the failure to the offending form field instead of a generic
 * banner. Only the rejected attribute name travels; the value never does.
 */
final class ReferralProgramAttributeException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $attribute,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function for(string $attribute, string $message, ?Throwable $previous = null): self
    {
        return new self($attribute, $message, $previous);
    }
}

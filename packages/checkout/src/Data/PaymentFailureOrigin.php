<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Data;

use Spatie\LaravelData\Data;

/**
 * The execution state a failing run observed when it lost the payment flow.
 *
 * The attempt generation alone cannot distinguish the execution that owns a
 * failure from an older execution that started before a replacement claim or
 * a same-generation recovery. The revision is incremented by every
 * authoritative attempt mutation (claim, adoption, recorded result, verified
 * outcome, definitive failure), so a failure operation is bound to the exact
 * observation that authorized it.
 */
final class PaymentFailureOrigin extends Data
{
    public function __construct(
        public int $attempts = 0,
        public int $revision = 0,
        public bool $claimed = false,
    ) {}

    public static function preClaim(): self
    {
        return new self(attempts: 0, revision: 0, claimed: false);
    }

    public static function fromClaim(PaymentAttemptClaim $claim): self
    {
        return new self(
            attempts: $claim->number,
            revision: $claim->revision,
            claimed: $claim->number > 0,
        );
    }

    public function isPreClaim(): bool
    {
        return ! $this->claimed;
    }
}

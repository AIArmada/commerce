<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Exceptions;

use InvalidArgumentException;

/**
 * The validated award amount violates an explicit caller policy limit.
 *
 * Thrown before any reservation or accounting, so a policy-limit rejection
 * always leaves zero money effects. The message names the violated bound;
 * it carries only the caller's own proposed values, never stored foreign
 * award data.
 */
final class AffiliateAwardPolicyLimitException extends InvalidArgumentException
{
    public static function forAmount(int $amountMinor, string $currency, ?int $policyMinMinor, ?int $policyMaxMinor): self
    {
        if ($policyMinMinor !== null && $amountMinor < $policyMinMinor) {
            return new self(sprintf(
                'Award amount [%d %s] is below the caller policy minimum of [%d %s].',
                $amountMinor,
                $currency,
                $policyMinMinor,
                $currency,
            ));
        }

        return new self(sprintf(
            'Award amount [%d %s] exceeds the caller policy maximum of [%d %s].',
            $amountMinor,
            $currency,
            (int) $policyMaxMinor,
            $currency,
        ));
    }
}

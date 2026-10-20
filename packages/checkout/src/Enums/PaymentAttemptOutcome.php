<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Enums;

/**
 * Authoritative classification of the session's current attempt generation.
 *
 * Recovery behavior is derived from this classification — computed from the
 * frozen claim and the recorded outcome under the session row lock — instead
 * of ad-hoc status lists in each entry point, so claims, recovery, and
 * observation all agree on which generation owns the outcome.
 */
enum PaymentAttemptOutcome: string
{
    /** No frozen claim exists for the current generation; a fresh attempt may be claimed. */
    case NewGeneration = 'new_generation';

    /**
     * The current generation has no recorded outcome (crashed or abandoned
     * claim) or an explicitly uncertain one; it must be re-driven with its
     * frozen identity instead of releasing a new generation.
     */
    case Reconcile = 'reconcile';

    /** A verified failure or cancellation; a replacement generation may be claimed when budget permits. */
    case DefinitiveFailure = 'definitive_failure';

    /** The recorded outcome already determines this generation (in flight, pending, or complete); observe only. */
    case Recorded = 'recorded';

    /** Whether driving this classification releases a new charge and therefore consumes retry budget. */
    public function requiresNewAttemptBudget(): bool
    {
        return $this === self::NewGeneration || $this === self::DefinitiveFailure;
    }
}

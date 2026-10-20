<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Enums;

/**
 * Outcome of claiming a gateway attempt for a checkout session.
 *
 * The claim serializes every caller of the payment step under the session
 * row lock, so exactly one caller owns the external gateway call for a given
 * attempt generation while everyone else observes the authoritative state.
 */
enum PaymentClaimStatus: string
{
    /** A new attempt generation was claimed and this caller owns the gateway call. */
    case Claimed = 'claimed';

    /** An in-flight attempt without a recorded outcome was adopted for an explicit retry. */
    case Adopted = 'adopted';

    /** The session is already completed; the persisted result is replayed. */
    case Completed = 'completed';

    /** The session is terminal without completion; processing must be rejected. */
    case Rejected = 'rejected';

    /** Another flow owns the current attempt or its recorded pending outcome. */
    case Observed = 'observed';
}

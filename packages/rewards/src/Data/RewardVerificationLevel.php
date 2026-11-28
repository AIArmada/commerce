<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

enum RewardVerificationLevel: string
{
    case Unverified = 'unverified';
    case Accepted = 'accepted';
    case StaffVerified = 'staff_verified';
    case ModeratedApproved = 'moderated_approved';

    /**
     * Ordered trust rank. Accepted registration is deliberately weaker than
     * staff verification: it must never be silently treated as verified
     * attendance. Cash/benefit components require staff_verified or higher.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Unverified => 0,
            self::Accepted => 1,
            self::StaffVerified => 2,
            self::ModeratedApproved => 3,
        };
    }

    public function satisfies(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    /**
     * Per-outcome verification: moderation vouches only for the configured
     * `rewards.moderated_outcomes` (moderated contributions), never for
     * unrelated outcome kinds. A moderated contribution qualifies as a
     * contribution but does not magically confer other verification.
     */
    /** @param list<string> $moderatedOutcomes */
    public function satisfiesFor(self $minimum, string $outcome, array $moderatedOutcomes): bool
    {
        if ($minimum === self::Unverified) {
            return true;
        }

        if ($this === self::ModeratedApproved) {
            if (! in_array($outcome, $moderatedOutcomes, true)) {
                return false;
            }
        }

        return $this->rank() >= $minimum->rank();
    }
}

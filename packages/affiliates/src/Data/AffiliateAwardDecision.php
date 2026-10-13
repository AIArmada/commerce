<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use InvalidArgumentException;

/**
 * Host authorization decision for one fixed-amount award.
 *
 * Only an affirmative decision (`authorized: true`) may post money. Every
 * other outcome — an explicit denial, or the package's default null policy
 * when the host bound nothing — is a truthful refusal with zero money
 * effects. A denial must carry a non-empty machine-greppable reason.
 *
 * Optional caller policy limits are enforced exact-or-reject by the award
 * action inside the posting transaction before any write: an amount
 * outside `[policyMinMinor, policyMaxMinor]` fails with an explicit
 * policy-limit error and zero accounting. Limits are caller policy,
 * never commercial commission caps, and a missing limit means unbounded
 * on that side.
 */
final readonly class AffiliateAwardDecision
{
    public function __construct(
        public bool $authorized,
        public ?string $reason = null,
        public ?int $policyMinMinor = null,
        public ?int $policyMaxMinor = null,
    ) {
        if ($this->policyMinMinor !== null && $this->policyMinMinor < 0) {
            throw new InvalidArgumentException('Award policy minimum must be null or a non-negative minor-unit amount.');
        }

        if ($this->policyMaxMinor !== null && $this->policyMaxMinor < 0) {
            throw new InvalidArgumentException('Award policy maximum must be null or a non-negative minor-unit amount.');
        }

        if ($this->policyMinMinor !== null && $this->policyMaxMinor !== null && $this->policyMinMinor > $this->policyMaxMinor) {
            throw new InvalidArgumentException('Award policy minimum must not exceed the policy maximum.');
        }

        if (! $this->authorized && ($this->reason === null || mb_trim($this->reason) === '')) {
            throw new InvalidArgumentException('A denied award decision must carry a non-empty reason.');
        }
    }

    public static function allow(?int $policyMinMinor = null, ?int $policyMaxMinor = null, ?string $reason = null): self
    {
        return new self(true, $reason, $policyMinMinor, $policyMaxMinor);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}

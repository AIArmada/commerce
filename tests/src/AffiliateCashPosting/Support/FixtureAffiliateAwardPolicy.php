<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\AffiliateCashPosting\Support;

use AIArmada\Affiliates\Contracts\AffiliateAwardPolicy;
use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Models\Affiliate;

/**
 * Explicitly labeled FIXTURE host boundary for cash-post tests.
 *
 * This fixture stands in for a real host authorization/eligibility
 * implementation. It proves the ACTION honors affirmative, denied, and
 * policy-limited decisions with real accounting underneath; it proves
 * nothing about any real host's membership, funding, or fraud rules.
 * Production hosts must bind their own `AffiliateAwardPolicy`.
 *
 * Optional observation mode records, for every `decide()` call, the
 * invoking connection name and transaction level plus a same-connection
 * locked re-read of the recipient — proving the action invokes the host
 * inside the posting transaction and lock boundary. Observation is
 * inert unless explicitly enabled per test and resets via the harness.
 */
final class FixtureAffiliateAwardPolicy implements AffiliateAwardPolicy
{
    public static bool $observe = false;

    /**
     * @var list<array{transaction_level: int, connection: string, recipient: string, award_key: string, observed_attributable: ?bool}>
     */
    public static array $observedDecisions = [];

    private function __construct(
        private readonly AffiliateAwardDecision $decision,
    ) {}

    public static function allow(?int $policyMinMinor = null, ?int $policyMaxMinor = null): self
    {
        return new self(AffiliateAwardDecision::allow($policyMinMinor, $policyMaxMinor, 'cashpost-fixture-allow'));
    }

    public static function deny(string $reason = 'cashpost-fixture-deny'): self
    {
        return new self(AffiliateAwardDecision::deny($reason));
    }

    public static function resetObservation(): void
    {
        self::$observe = false;
        self::$observedDecisions = [];
    }

    public function decide(Affiliate $recipient, AffiliateAwardProposal $proposal): AffiliateAwardDecision
    {
        if (self::$observe) {
            $fresh = Affiliate::query()->whereKey($recipient->getKey())->lockForUpdate()->first();

            self::$observedDecisions[] = [
                'transaction_level' => $recipient->getConnection()->transactionLevel(),
                'connection' => (string) $recipient->getConnection()->getName(),
                'recipient' => (string) $recipient->getKey(),
                'award_key' => $proposal->awardKey,
                'observed_attributable' => $fresh instanceof Affiliate ? $fresh->canBeAttributed() : null,
            ];
        }

        return $this->decision;
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Awards;

use AIArmada\Affiliates\Contracts\AffiliateAwardPolicy;
use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Models\Affiliate;

/**
 * Truthful default refusal when the host bound no award policy.
 *
 * Awards require proven caller authorization and recipient eligibility.
 * A missing host boundary proves nothing, so the default is a denial with
 * zero money effects — never a silent allow, never a fabricated proof.
 */
final class NullAffiliateAwardPolicy implements AffiliateAwardPolicy
{
    public function decide(Affiliate $recipient, AffiliateAwardProposal $proposal): AffiliateAwardDecision
    {
        return AffiliateAwardDecision::deny('award_host_policy_unbound');
    }
}

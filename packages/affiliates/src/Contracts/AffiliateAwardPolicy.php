<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Models\Affiliate;

/**
 * Neutral host boundary for fixed-amount award authorization.
 *
 * The generic award action owns money movement only. Whether a caller may
 * post an award, whether a recipient is eligible to receive it, which
 * policy limits apply, and what fraud/eligibility evidence was evaluated
 * are host decisions implemented behind this contract. The package ships
 * no interpretation of host membership, funding, or program state, and it
 * never invents eligibility or fraud evidence.
 *
 * Binding is optional. When no implementation is bound, the award action
 * resolves the null policy and returns a truthful refusal with zero money
 * effects. Hosts bind exactly one implementation in their own service
 * provider; the affiliates package registers no default binding so an
 * unconfigured host can never post money.
 *
 * Atomicity contract: the award action invokes `decide()` INSIDE the
 * posting transaction, on the award connection, after acquiring the
 * idempotency and recipient row locks (`SELECT ... FOR UPDATE`). At
 * invocation the connection transaction level is at least 1, and any
 * same-connection locked reads the host performs (approval rows, budget
 * rows, eligibility sources) are held through commit — a concurrent
 * deactivation or correction that commits first is observed instead of
 * slipped through. Only same-connection reads participate: eligibility
 * state read over any other connection is not atomic with posting.
 *
 * Implementations must be deterministic for a given recipient + proposal,
 * must be read-only (no row writes anywhere: posting retries after a
 * deadlock re-invoke `decide()`), and must keep any long-lived state in
 * container scope (never request-leaking statics) for Octane safety.
 * The recipient passed in is the locked in-transaction row: fresh, but
 * attribution status alone is never sufficient — the returned decision
 * is the financial authorization for new money.
 */
interface AffiliateAwardPolicy
{
    public function decide(Affiliate $recipient, AffiliateAwardProposal $proposal): AffiliateAwardDecision;
}

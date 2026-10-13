---
title: Fixed-Amount Awards
---

# Fixed-Amount Awards

Post generic fixed-amount monetary awards through a single exact-or-reject
action. Fixed awards are entitlements, not earned commissions: the action
posts exactly the validated amount or refuses before any money effect.

> [!warning]
> Award rows are financial backing records only. They are not provider
> payments, not proof of a funded program, and they never dispatch
> downline, bonus, payout, or transport side effects.

## The action

```php
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateAward;
use AIArmada\Affiliates\Enums\AffiliateOutcomeDisposition;

$result = app(RecordAffiliateAward::class)->handle(
    $recipient,            // Affiliate model: the eligible recipient
    'acme:award:2026-10-07:9f2c', // caller-owned bounded stable award key
    1500,                 // positive integer minor-unit amount, posted exactly
    'MYR',                // explicit 3-letter currency
    ['status' => 'approved'], // optional attributes (see below)
);

if ($result->disposition === AffiliateOutcomeDisposition::Created) {
    // $result->conversion is AffiliateConversionData for the new backing row.
}
```

Or via the action shortcut: `RecordAffiliateAward::run($recipient, $key, $amount, $currency, $attributes)`.

## Award identity

The caller supplies a bounded caller-owned namespaced award key (non-empty,
at most 191 characters, no NUL bytes, no required delimiter). The package
hashes the exact validated identity with `sha256` for the unique
`idempotency_key`, independent of recipient, owner, or link.

- An identical retry returns `Replayed` with the stored row untouched:
  frozen recipient, owner, amount, currency, and backing, with zero new
  accounting, zero fraud writes, and zero events.
- Reusing a recorded key with a different recipient, owner, amount,
  currency, or backing type throws `InvalidArgumentException` before any
  new effect and discloses no stored foreign data.

## Amount exactness

The posted `commission_minor` always equals the validated `$amountMinor`.
The action never applies commercial `CommissionCaps`, never derives an
amount from a percentage, and never mutates an existing zero-value row:
every award is a separate new conversion with
`conversion_type = 'reward_award'` and a null attribution.

## Host authorization boundary

Money movement is package-owned; authorization is host-owned. Hosts prove
caller authorization and recipient eligibility behind a neutral contract:

```php
namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Models\Affiliate;

interface AffiliateAwardPolicy
{
    public function decide(Affiliate $recipient, AffiliateAwardProposal $proposal): AffiliateAwardDecision;
}
```

Bind exactly one implementation in the host service provider:

```php
use AIArmada\Affiliates\Contracts\AffiliateAwardPolicy;

$this->app->singleton(AffiliateAwardPolicy::class, AcmeAwardPolicy::class);
```

Rules:

- No binding resolves to the null policy, which denies every award. A
  missing host boundary is a truthful `Rejected` refusal with zero money
  effects — never a silent allow.
- An explicit denial also returns `Rejected` with a null conversion and
  zero effects.
- Only an affirmative decision posts new money. Optional caller policy
  limits on the decision (`policyMinMinor`/`policyMaxMinor`) are
  enforced exact-or-reject inside the transaction before any write: a
  violation throws `AffiliateAwardPolicyLimitException` with zero
  accounting.
- The package keeps no interpretation of host membership or funding, runs
  no fraud rules of its own, and never invents eligibility or fraud
  evidence. Host fraud/eligibility evidence must travel inside the host
  decision. Package fraud rules, when a host uses them at all, must run
  post-insert on the persisted conversion ID in the same transaction —
  never on invented attribution.

Atomicity contract: `decide()` runs INSIDE the posting transaction on
the award connection, after the idempotency and recipient row locks.
The connection transaction level is at least 1 at invocation, and any
same-connection `SELECT ... FOR UPDATE` the host performs (approval,
budget, or eligibility source rows) is held through commit. Reads over
any other connection are not atomic with posting. Implementations must
be deterministic per recipient + proposal and read-only — deadlock
retries re-invoke `decide()`, so any write would repeat.

## Recipients

The recipient is re-read with a row lock inside the posting
transaction. For a new award, a missing recipient — or one that fails
`canBeAttributed()` — returns `NoAffiliate` with zero effects, and an
attributable recipient still needs an affirmative host decision:
attribution status alone never posts money. The recipient's owner tuple
is frozen onto the backing row at creation.

An identical retry of an already-recorded award settles as `Replayed`
without fresh authorization: frozen row, zero new accounting, zero
events. Earned obligations therefore survive later deactivation or
denial. Binding mismatches still throw without disclosing stored
foreign data, and a missing recipient row with an existing key fails
closed as `NoAffiliate`.

## Attributes

Only these attributes are accepted; anything else (including attribution,
subject, link, upline, voucher, and commission inputs) is refused:

| Attribute | Rules |
|---|---|
| `status` | `'pending'` or `'approved'` (string or state class). Defaults to approved. Paid, reversed, and unknown statuses are refused at creation. |
| `external_reference` | Optional string, at most 120 characters. Defaults to the award key truncated to 120. |
| `source_ref` | Optional string, at most 120 characters. |
| `channel` | Optional string, at most 64 characters. |
| `origin` | Optional string, at most 32 characters. |
| `metadata` | Optional array: at most 32 entries, scalar-or-null values, keys at most 64 characters, string values at most 500 characters. |
| `dispatch_event` | Optional bool, defaults to `true`. |
| `occurred_at` | Optional date/time instance, defaults to now in UTC. |

Approved awards stamp a dedicated UTC `approved_at`; pending awards record
a `held_minor` stamp with no `approved_at`. Balances follow the shared
creation matrix: approved credits available plus lifetime; pending credits
holding plus lifetime.

## Results and events

The action returns the shared `AffiliateOutcomeResult`:

| Disposition | Meaning |
|---|---|
| `Created` | New backing row posted with exact accounting. |
| `Replayed` | Identical retry; stored row returned untouched, even after later deactivation or denial. |
| `Rejected` | Host refused (unbound or denied policy) for a new award; null conversion. |
| `NoAffiliate` | Missing or inattributable recipient for a new award; null conversion. |

Only `Created` advances projections. Only creation dispatches
`AffiliateConversionRecorded` (after commit, gated by `dispatch_event`);
replays stay silent. Validation, policy-limit, mismatch, and connection
failures throw before any effect.

## Transactions and connections

One same-connection transaction covers the locked idempotency
preflight, the authoritative locked recipient read, the host
authorization call, savepoint insertion with unique-recovery, and
creation accounting through the shared `ApplyConversionAccounting`
matrix — in conversion, affiliate, balance lock order. Recipient,
conversion, and balance must resolve to one shared connection name and
instance, or the action fails closed with `LogicException`.
After-commit dispatch is registered only after commit, so deadlock
retries never double-announce.

## What this action does not do

- No commercial clamp, percentage, downline, bonus, rank, or payout work.
- No fraud-rule execution and no fraud-signal writes.
- No provider calls, funding checks, or budget movements.
- No routes: semantic keys and authority are server-side inputs only, and
  public clients must never supply them.
- No interpretation of host programs, membership, or finance approvals.

## See also

- [Usage](04-usage.md)
- [Payouts](08-payouts.md)
- [Fraud detection](09-fraud-detection.md)
- [Multi-tenancy](10-multi-tenancy.md)

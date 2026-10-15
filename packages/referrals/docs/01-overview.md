---
title: Referrals Overview
---

# Referrals Overview

`aiarmada/referrals` provides member-get-member (MGM) referrals for Laravel hosts. A program issues addressed invites, binds one invite to one invitee, accepts a host-reported qualifying event, freezes both reward promises, and delegates fulfilment and reversal through adapters.

## What this package owns

- Owner-scoped referral programs and their active date windows and caps.
- Contact normalization and hashing, one-time invite tokens, encrypted contact snapshots, single-claim acceptance, and invite lifecycle.
- Host-reported qualification keyed by a unique business key.
- Immutable, two-sided reward intents copied from the program when qualification succeeds.
- Fulfilment and reversal orchestration with idempotency keys, external references, events, and an expiry command.

`ReferralProgram` is the tenant boundary. Invites and rewards have no owner columns; all access resolves through their program.

## What it does not own

- Identity, authentication, contact capture, registration, or deciding whether a person has completed a meaningful action. The host supplies persisted models, contact values, and qualification events.
- Commercial partner attribution, click tracking, commission plans, payout policy, or affiliate accounting. Those belong to `affiliates`.
- Points balances, entitlement rules, or correction ledgers. Those belong to `rewards`.
- Voucher validation, redemption, or wallet behavior. Those belong to `vouchers`.

MGM referrals are social invites among members. Commercial affiliate programs attribute traffic and revenue to partners. A host may use both, but the two domains have separate records and policies.

## Fulfilment boundaries

The package is usable with only `commerce-support` and `laravel-actions`. Manual fulfilment is the default. Voucher, affiliate-cash, and reward-points adapters activate only when their public package APIs are available and their driver is enabled. A missing or misconfigured driver raises `ReferralFulfillmentException`; a promised reward is never silently skipped.

Reward specifications have this shape:

```php
[
    'driver' => 'manual|voucher|affiliate_cash|reward_points',
    'amount_minor' => null, // integer minor units for cash or voucher value
    'points' => null,       // integer points for the rewards rail
    'currency' => null,     // explicit three-letter currency when monetary
    'voucher' => null,      // input array for vouchers' CreateVoucher action
    'family_key' => null,   // rewards program family slug; reward_points only
]
```

Use `null` or an empty array to omit a side. The program's specs are validated when the program is created. A qualified invite stores a frozen copy on each reward row, so later program edits cannot change a promise already made. One Rewards program's points component carries one points value, so two points sides with different amounts must each name a `family_key`; equal amounts may share a family or omit it. Voucher sides are always single-use and beneficiary-bound, worth exactly the promised `amount_minor` — the spec cannot ask for anything else.

## Lifecycle

`Pending → Accepted → Qualified → Rewarded` is the normal path. Pending or accepted invites may instead expire or be revoked. A qualified invite returns to `Qualified` if any fulfilled side is reversed. Reward rows move through `Pending → Fulfilled → Reversed`, with `Failed` available for retry after an adapter refusal.

See [Usage](04-usage.md) for a complete example and [Configuration](03-configuration.md) for owner and driver settings.

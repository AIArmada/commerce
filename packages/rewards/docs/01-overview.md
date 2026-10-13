---
title: Rewards overview
---

# Rewards overview

`aiarmada/rewards` runs configurable reward programs: recognition, points, badges, cash payouts, and benefit grants. Every entitlement is evaluated against frozen program versions, recorded on append-only ledgers, and kept honest by budget and idempotency invariants.

## How it fits together

- **Program families** (`RewardProgram`) are the tenant boundary and carry the lifecycle draft → ready → active → paused/closed. Rule and terms snapshots live on immutable **versions**; publications record the append-only timeline.
- **Enrollment** binds a member (a polymorphic recipient tuple) to a version under explicit terms acceptance.
- **Evaluation** reads a current **source fact** through the host-bound `RewardSourceFactReader`, checks eligibility, caps, verification, fraud, and budget, then mints a **frozen award** or refuses with a machine-readable reason.
- **Transitions** move awards held → pending → approved → fulfilled (or rejected/reversed/corrected), stamping dedicated timestamp columns and appending ledger entries.
- **Money** stays integer minor units plus an explicit currency. Funding entries and budget movements (reserve/spend/release/recover) enforce funded ≥ reserved + net spent; payouts execute through the bound `RewardPayoutExecutor` with frozen terms and idempotency keys.
- **Benefits** reserve from a capacitated catalog, fulfill through manual handoff or the bound `RewardBenefitCouponFulfillment`, and consume only on canonical use.

## Host boundaries

The package never implements source, payout, coupon, or notification rails. Hosts bind these contracts; fail-closed defaults ship for all of them:

- `RewardSourceFactReader` — current source facts by business key.
- `RewardPayoutExecutor` / `RewardPayoutMethodCatalog` — payout submission, cancel, status, settlement; method registry with fee schedules.
- `RewardBenefitCouponFulfillment` — coupon issue, canonical-use proof, revoke.
- `RewardRecipientResolver` — recipient existence for fulfillment-time checks.
- `RewardCorrectionKeySource` — tainted business keys for the corrections sweep (`taintedKeys`) and single-key rechecks (`causeFor`).
- `RewardGratitudePlanner` — post-achievement notification hook.

Related: [installation](02-installation.md), [configuration](03-configuration.md), [usage](04-usage.md), [troubleshooting](99-troubleshooting.md).

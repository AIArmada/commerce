---
title: Filament rewards usage
---

# Filament rewards usage

## Programs

Create program families from the list page: family key, title, mode, owner (or platform for a global program), and purpose. Versions, readiness, and activation run through the core actions from the program view page, including a qualification preview sandbox.

Only staff panel users may open the create page. The submitted owner alias is re-resolved server-side against `filament-rewards.owners.types` and the referenced record is loaded before the core action runs — the handler never trusts the posted alias or id, and an unconfigured alias or missing record fails on `owner_record_id` with no row written. Platform programs are the only owner-less form.

Publishing a version uses repeaters for component variants and milestone
levels. A component variant specifies its eligible outcomes and beneficiary
roles, minimum verification, first-only behavior, and amount or grant payload.
Milestone rows define the member-facing label, qualifying outcomes and roles,
threshold, cycle window, verification rule, and recognition, points, or badge
grant. The core validator checks the submitted rows before the immutable
version is published. Existing flat component versions remain readable.

## Qualification preview sandbox

The **Preview qualification** action on the program view page explains why a supplied fixture qualifies, holds, or rejects under the applicable published version. Nothing is persisted, and every result is labeled fixture-only (`FIXTURE-NOT-SOURCE`), never source proof.

The sandbox speaks the core array contract exactly: `occurred_at` is an explicit absolute timestamp string, `verification` is a string level value, and the beneficiary-role select (`recipient` default, or `participant` / `purchaser` / `staff` / `sharer`) rides through as `beneficiary_role`. Actor-bound roles can earn unattributed evidence; sharer-bound roles still require **Attributed** to be on. Because the sandbox supplies no enrollment, the deterministic verdict ends at the enrollment gate — the notification still lists every refusal reason for the operator. Preview never writes rows; live evaluation re-resolves under locks.

## Awards and appeals

The awards table transitions held → pending → approved → fulfilled (or rejected/reversed/corrected) with actor, reason, and evidence capture. Appeals open from awards and advance through review to upheld/overturned/closed.

## Money

The funding page records funding sources and program allocations. The payee queue verifies or rejects recipient destinations (verifiers cannot verify their own). Payee registration offers the methods configured in `rewards.payouts.methods`; the package default is `manual`. The payout queue approves, submits, confirms, cancels, and reconciles payout requests; manual rails await independent confirmation with transfer evidence.

## Benefits and recoveries

Benefit items define the capacitated catalog; grants reserve, fulfill (manual handoff or coupon), consume on canonical use, and revoke or expire. Recoveries track post-fulfillment value clawback through settlement or write-off.

Members see their own point balance and milestone progress on their rewards
surface. The admin program view controls published milestone labels and
thresholds; it does not create a public leaderboard.

## Fraud queue

Held and pending awards with their live fraud status from the bound fact reader, plus fraud dismissal (dismissal never approves — a separate human approval is still required).

Related: [configuration](03-configuration.md), [troubleshooting](99-troubleshooting.md).

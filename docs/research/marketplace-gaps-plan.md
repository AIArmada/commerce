# Marketplace Money Gaps — Recommendations & Plan

> Status: implemented. Every money rule below shipped with a proving Pest
> test; open decisions resolved as: fees default 0 (offer override),
> remote dual-reporting flags in reconciliation (never blocks), balances
> derived from legs at read time, notifications via `communications`
> on by default. This document is the decision record.

## Goal

Close the four known marketplace money gaps — no monetization plumbing,
double-payment risk, trust-on-report postbacks, and finish work (dead events,
unpaid volume tiers) — plus the widened-scope items found during research
(refunds, reconciliation wiring, token rotation), with an explicit rule for
every money decision.

## Success Criteria

- Every network conversion computes an explicit network fee leg (possibly zero
  by config) persisted on the ledger row; reconciliation proves
  counted == paid exactly once including fees.
- One purchase can never pay through both the store program and the network:
  a single cross-system winner is recorded per order, the loser is marked
  superseded, and both paths are covered by tests.
- The postback trust boundary is documented and hardened (rotation, anomaly
  signals); amounts remain merchant-reported by explicit decision, not by
  accident.
- Offer volume tiers are either paid or removed; every fired network event has
  a listener or a documented reason for silence.
- No behavior change ships without a Pest test proving the money rule.

## Context And Current Facts

All verified against code in `packages/affiliate-network`,
`packages/affiliates`, `packages/filament-affiliate-network`, and rizq.

**Double-pay is structural, not theoretical.** Both `RecordNetworkConversionForOrder`
(network) and `RecordCommissionForOrder` (engine) listen to the same
`CommissionAttributionRequired` order event. The network guard is
`network_attribution` order metadata; the engine resolves via `cart_id` metadata
plus its own attribution row. Neither checks the other. Persistence cannot save
it either: the engine idempotency key is
`sha256(owner|owner|affiliate|type|channel|ref:order)` (`RecordAffiliateConversion::resolveIdempotencyKey`)
while the network ledger key is `sha256('network|link|ref')`
(`AffiliatesLedger::post`) — different constructions, never collide, so both
rows persist. Trigger: a buyer holding both cookies (`affiliate_session` and
`affiliate_network_link`) on a local-checkout install (network checkout
integration is config-gated, default off). A second variant exists for remote
merchants running the engine: they can post to the network AND record locally
for the same sale across two databases — undetectable by either side today.

**Monetization plumbing does not exist.** Zero matches for take-rate, rev-share,
or fee concepts in either package. The natural insertion points are
`NetworkConversionDraft` (add fee fields), `PostNetworkConversionToLedger`
(split math — currently base/fixed rate only), and `AffiliatesLedger::post`
(persist legs). No billing, invoicing, or merchant-settlement concepts exist.

**Postback trust boundary.** `ReportNetworkConversionController` authenticates
via the site's encrypted catalog token, checked against the Authorization
header with `hash_equals`, requires a verified site, and
validates link/site/offer state; redelivery is idempotent on (link, external
reference). But `revenue_minor` flows straight into counters and the ledger
with no cross-check (no click-side amount exists to check against), there is no
token rotation API on `AffiliateSite`, and no anomaly detection. A
`NetworkLedgerReconciliationService` (counters vs ledger rows) exists but is
only rendered in a Filament relation manager — no schedule, no alerts.

**Dead events.** 5 network events + 10 engine events fired, zero listeners
registered anywhere: neither package listens to its own events, the Filament
packages register no listeners, and rizq registers none. There are no Notifier
or Notification classes in the network package — "notification hooks" means the
bare events. Correction to the premise: nothing exists to wire; listeners must
be built (the monorepo has a `communications` package as the likely channel).

**Volume tiers.** `volume_tiers` (`{min_volume_minor, rate_bp, currency}[]`) is
written by `CreateOffer`/`UpdateOffer`/import and editable in the Filament
offer form — but read nowhere for payout, nowhere in rizq, and not rendered in
the Filament offer table. The engine DOES apply its program volume tiers
(`CommissionRuleEngine::volumeTiersForProgram`), so the network is the odd one
out. Correction to the premise: tiers are captured but neither displayed on any
listing surface found nor paid.

**Widened-scope findings.** No refund/reversal/clawback flow (only a stat column
mention in `AffiliateDailyStat`); currency mismatch is already handled sanely
(count conversion, zero revenue, warn-log, ledger keeps real money);
attribution windows align by default (30d both sides) but are enforced by
different clocks (cookie `clicked_at` vs DB expiry); network cookie is
last-touch, engine likewise — cross-system ties have no rule (same fix as
double-pay).

## Constraints And Non-goals

- Pest-only; new money rules ship with tests in `tests/src/AffiliateNetwork`.
- No backward-compat shims (per standing instruction); network APIs stay
  string-identity based.
- Non-goals for this plan: actual payment processing / merchant invoicing /
  creator payouts execution (phase 2 on top of the fee legs built here);
  engine-side event listeners (same pattern, separate plan); changing the
  30-day window defaults.

## Key Decisions

1. **Double-pay rule: last-touch wins across both systems.** When both
   attributions exist, pay only the most recent touch (`clicked_at`); record
   the loser as superseded on the order metadata and as a rejected ledger leg
   for reporting. Rejected alternative: network-always-wins (simpler, but
   misattributes genuine store-driven sales); split (industry-rare, confusing
   reporting).
2. **Monetization model: network take-rate as basis points on commission.**
   Computed in `PostNetworkConversionToLedger`, persisted as explicit fee legs,
   default from config with per-offer override. Rejected: flat per-conversion
   fee only (inflexible); merchant subscription (no billing system exists —
   revisit in phase 2). The take-rate VALUE is a business input, default 0
   until set — plumbing ships rate-agnostic.
3. **Postback amounts stay merchant-reported (conscious).** No independent
   verification is feasible without order-system integration; harden around it
   (rotation, anomaly signals, reconciliation alerts) instead of pretending to
   verify.
4. **Volume tiers: pay them, per affiliate+offer cumulative revenue.**
   Mirror the engine rule shape; window = offer lifetime unless configured.
   Rejected: remove tiers (data already captured on offers; merchants were
   promised the feature by its presence in admin).

## Recommended Approach

Build in five workstreams, each independently shippable and test-covered, in
dependency order: double-pay guard first (it constrains the ledger), fee legs
second (they extend the same draft/post path), postback hardening third
(orthogonal), finish work plus widened-scope closes last. Every money rule gets
its Pest test before the behavior ships; reconciliation is extended to prove
fees and supersedes.

## Work Plan

**WS-A: Cross-system attribution winner (double-pay rule).**
- Add `attribution_winner` (`network`|`engine`) to order metadata, written
  atomically by whichever listener runs first; the second listener honors it.
- Compare touch times: network cookie `clicked_at` vs engine attribution
  `created_at`/touchpoint time; most recent wins.
- Loser path: network records superseded marker (no counters, no ledger post);
  engine records a `RejectedConversion` with reason `superseded_by_network`
  (and vice versa).
- Remote-merchant variant: document as detectable-only-via-reconciliation;
  add `external_reference` collision report to the reconciliation service.
- Tests: both-cookies-last-touch-each-way, single-cookie unchanged,
  redelivery idempotency, superseded reporting rows.

**WS-B: Network fee legs (monetization plumbing).**
- `NetworkConversionDraft` + posted conversion: add `network_fee_minor`,
  `network_fee_bp`, `creator_payout_minor` (= commission − fee).
- `PostNetworkConversionToLedger`: compute fee from offer `network_fee_bp`
  (new nullable column, default `affiliate-network.fees.default_bp`, default
  0); fee never exceeds commission (clamp).
- `AffiliatesLedger::post`: persist fee legs on the conversion row/metadata.
- Extend `NetworkLedgerReconciliationService` with fee-leg proof
  (commission == payout + fee per row).
- Filament: fee columns on conversion/ledger tables; config docs.
- Tests: zero-fee default, bp math + clamp, reconciliation proof, per-offer
  override.
- Explicitly NOT in WS-B: invoicing, settlement, payouts execution.

**WS-C: Postback trust hardening.**
- Document the trust boundary in `packages/affiliate-network/docs`
  (authenticated + verified, amounts reported).
- `AffiliateSite`: token issuance/rotation API + Filament action; rotation
  invalidates the old token immediately.
- Anomaly signals (not blocks): amount spike vs link history and first-seen
  external-reference formats feed `FraudSignalDetected`-style signals.
- Wire reconciliation to a scheduled console command with failure alerts;
  add remote-vs-local collision report from WS-A.
- Tests: rotation invalidates, redelivery idempotent, anomaly signal emitted,
  scheduled command exit codes.

**WS-D: Finish work (tiers + events).**
- Apply `volume_tiers` in `PostNetworkConversionToLedger`: cumulative
  affiliate+offer revenue selects the tier rate; tier evaluation recorded on
  the draft for audit.
- Render effective tier on the Filament offer view; keep admin editing.
- Listeners for the 5 network events via the `communications` package
  (application submitted/approved → merchant + creator notifications;
  conversion recorded → creator statement hook); document the 10 engine events
  as intentionally host-owned with a follow-up note.
- Tests: tier selection boundaries, cumulative revenue accounting, each new
  listener fired with correct payload.

**WS-E (small, with WS-D): widened-scope closes.**
- Refunds: implement the `reversed` conversion state + negative ledger leg
  shape on the ledger contract with tests, but no auto-trigger wiring (no
  refund source exists yet).
- Windows: document the enforcement matrix (cookie vs DB vs link TTL) in docs;
  no code change.
- Currency mismatch: route the existing warn-log to the reconciliation
  differences report so finance sees it.

## Validation Plan

- `XDEBUG_MODE=off vendor/bin/pest tests/src/AffiliateNetwork --parallel`
  and `XDEBUG_MODE=off vendor/bin/pest tests/src/Affiliates --parallel` per
  workstream (new tests listed above must fail pre-fix, pass post-fix).
- `vendor/bin/pint --test`, `composer test:phpstan` before each workstream
  commit.
- Reconciliation proof: seed link + conversions + fees, run
  `reconcileLink`, assert `match: true` including fee legs.
- Manual: post a conversion via `ReportNetworkConversionController` with
  rotated token (401), old token after rotation (401), duplicate delivery
  (duplicate: true, counters unchanged).
- Highest-risk validation: WS-A ordering test — run listeners in both orders
  and assert identical winner + single payout.

## Risks / Rollback

- WS-A changes payout outcomes: gate behind config
  (`affiliate-network.attribution.precedence`, default `last_touch`) with a
  `legacy` escape that restores independent recording; rollback = config flip.
- Fee legs touch the ledger draft contract: additive fields only, defaults
  preserve current math (fee 0).
- Volume tiers change creator payouts upward: announce before enabling; tiers
  only apply when configured on the offer.
- No migrations are destructive; all new columns nullable.

## Open Questions

- What should the default network take-rate be, and who sets it per offer
  (marketplace admin or merchant)? Default assumption: 0 until the business
  decides; admin-settable.
- Should the remote-merchant dual-reporting case (two databases) block payout
  or only flag in reconciliation? Assumption: flag only — blocking needs a
  cross-system lock that does not exist.
- Which notification channels (email, in-app, webhook) should WS-D listeners
  use? Assumption: `communications` package default; host app owns channels.

# Fresh Architecture: affiliates + affiliate-network from scratch

> Status: implemented. The rebuild below shipped without backward
> compatibility; this document is the design record. For current behavior,
> read the package docs (`packages/affiliates/docs`,
> `packages/affiliate-network/docs`).

## Premise

Two packages, three valid installs: engine alone (merchant program),
network alone (marketplace like rizq), both together (the wonderful mode).
No backward compat, no legacy — delete, move, and rename freely.

## Module map

### `affiliates` — the merchant engine

One sentence: everything a single merchant needs to run its own affiliate
program, knowing nothing of any marketplace.

Owns:

- Affiliate registry: profiles, codes, status lifecycle (this merchant's
  affiliates only).
- Programs: definitions, memberships, tiers, ranks, commission rules,
  volume tiers, caps.
- Tracking: signed link URLs, cookie, attribution rows, touchpoints.
- Fraud: behavioral signals + decisions on its own data.
- Conversions: commission computation + idempotent rows, including
  externally-originated obligations and reversals.
- Payouts: accrual, scheduling, claims, status, reconciliation. The only
  module that moves money merchant-side.
- Portal surface: join/apply endpoints and dashboard data (today's engine
  has no HTTP join — fresh adds it; a standalone program must let
  affiliates sign up).

Exposes at its seam (merchant vocabulary, no network words):

- `ProgramCatalog::snapshot()` — read-only program data for mirrors.
- `MerchantLedger::postExternalConversion()` — record an externally
  attributed conversion into merchant books, idempotent on source + ref.
- `MerchantLedger::postingsFor()` — postings for reconciliation.
- `MerchantIdentity::find()` / `findIdForEmail()` — recognition for
  reporting, never gating.
- Events: conversion recorded/reversed, payout created/updated, program
  joined, fraud signal.

Never imports a network class. Never mentions sites, offers,
applications, creators, or fees.

### `affiliate-network` — the marketplace

One sentence: everything needed to run a multi-merchant marketplace,
financially complete on its own.

Owns:

- Merchant directory: sites, verification, catalog tokens (with rotation),
  receivables.
- Catalog: offers with own rates, tiers, and fee policy; categories,
  creatives; import snapshots from merchant catalogs.
- Enrollment: applications — the only join flow (already correct).
- Tracking: links on aiarmada/links, cookies, clicks.
- Conversion recording: click counters plus **network books** — one posted
  leg row per conversion (revenue, commission, fee, payout, currency,
  external ref). Link conversion/revenue counters become cached aggregates
  derived from legs: single source of truth, no drift.
- Attribution decisions: one decision per order (trivially "network" when
  alone; last-touch across systems when the engine is present).
- Reconciliation: books vs counters, books vs merchant ledger when bound —
  scheduled, alerted, proving every counted conversion paid exactly once.
- Notifications: listeners on its own events via the communications
  package.

Adapts to (never requires) the engine through its own seam contracts,
with adapters shipped inside the network package and loaded only when
engine classes exist:

- `MerchantCatalogReader` → engine `ProgramCatalog` (mirror sync).
- `MerchantLedgerPoster` → engine `MerchantLedger` (fulfill network legs
  into merchant books).
- `MerchantIdentityReader` → engine `MerchantIdentity` (recognition).
- `Fulfillment` → engine payouts adapter when bound, else host adapter
  (manual/rails runs + statements + export). Exactly one bound per
  install, enforced in the service provider: single writer, double payout
  structurally impossible.

Dependency direction: network → affiliates (adapters), never the reverse.
The engine is dependency-clean; the network degrades gracefully to
standalone books + host fulfillment.

## The wonderful mode (both installed)

What lights up with zero extra config beyond installing both:

1. Mirrored catalogs: engine programs snapshot into network offers on
   schedule; merchant edits flow through; network-only fields (fee
   policy, tiers display) never overwritten.
2. Recognition: merchant affiliates appear linked in network reporting
   (by id, then verified email) — still never a join gate.
3. One winner: the attribution decider sees both touches and pays one;
   the loser is marked superseded on both sides' records.
4. Fulfillment: network legs post into merchant books through the ledger
   adapter; creator sees one statement; merchant sees one payout run.
5. Fraud sharing: engine conversion verdicts (rejected) supersede the
   matching network leg; network anomaly signals are visible to the
   merchant.
6. Unified proof: one reconciliation report joins network legs, merchant
   postings, and counters — match means money is exactly-once.

## What changes vs today

### Engine (`affiliates`)

1. DELETE `src/Network/` entirely. Its adapters move into the network
   package, rewritten against merchant-shaped contracts.
2. ADD portal join endpoints (apply to program, application status).
   Standalone means sign-up works out of the box.
3. ADD the four seam contracts above in `Contracts/` — small, engine
   vocabulary, no network DTOs.
4. ADD reversal support: `reversed` conversion state + negative ledger
   leg from day one (refunds exist in every real deployment).
5. GENERALIZE external origins: keep `origin` + `network_link_id`
   mechanics, rename to source-agnostic (`source`, `source_ref`) so any
   marketplace — not just this network — can post.
6. SHIP notification listeners behind the communications package
   (on if installed): program join approved, conversion recorded,
   payout created. A standalone program that never emails is half a
   product.
7. Events stay; document each as host-overridable.

### Network (`affiliate-network`)

1. ADD books: `network_conversion_legs` (revenue, commission, fee,
   payout, currency, ref, tier evaluation audit) + derived balances +
   fulfillment records. Counters-only mode is deleted; the network is
   financially complete alone.
2. ADD the `Fulfillment` seam with engine + host adapters, mutually
   exclusive by provider enforcement.
3. MOVE engine adapters in from the engine's deleted `Network/`,
   rewritten against the merchant contracts.
4. ADD the attribution decider as core: `AttributionDecider::decide()`
   with a `LastTouchDecider` default, config-swappable, consulted by
   both listeners through one `attribution_winner` order marker.
5. APPLY volume tiers in payout math from day one (cumulative
   affiliate+offer revenue selects the rate; evaluation stored on the
   leg for audit).
6. SIMPLIFY rate sync: replace `rate_source` synced/manual column
   ownership with `source` (manual|mirrored) + `synced_at`; mirrored
   offers refresh wholesale, network-only columns untouched.
7. ADD day-one: site token rotation, scheduled + alerted reconciliation,
   listeners on all 5 own events, documented postback trust boundary.
8. KEEP: user-key identity, applications-only enrollment, links-backed
   redirects, encrypted cookie, idempotent postbacks.

### Shared / deleted

- DELETE: engine `src/Network/*`, network counters-only mode,
  `rate_source` complexity, any "remote vs local" enrollment branching
  (already gone).
- RENAME: `NetworkLedger` → `MerchantLedger` (engine side);
  `NetworkConversionDraft` splits into the network-internal leg draft
  and the merchant-facing `ExternalConversion` DTO.
- MOVE: all cross-package adapters live in the network package.

## Interface sketches

Engine seam (new, merchant vocabulary):

```php
interface ProgramCatalog
{
    public function snapshot(string $programId): ProgramSnapshot;
}

interface MerchantLedger
{
    public function postExternalConversion(ExternalConversion $draft): PostedConversion;
    /** @return array<string, mixed> postings for reconciliation */
    public function postingsFor(string $source, string $sourceRef): array;
}

interface MerchantIdentity
{
    public function find(string $id): ?MerchantAffiliate;
    public function findIdForEmail(string $email): ?string;
}
```

Network books + fulfillment (new):

```php
final class NetworkBooks
{
    public function post(ConversionDraft $draft): PostedLegs;   // idempotent on link + ref
    public function reverse(PostedLegs $legs, string $reason): ReversedLegs;
}

interface Fulfillment
{
    public function fulfill(Payable $payable): FulfillmentReceipt;
}
// Adapters: EnginePayoutFulfillment | HostManualFulfillment (exactly one bound)
```

Attribution (new):

```php
interface AttributionDecider
{
    public function decide(OrderTouches $touches): AttributionDecision; // winner + loser
}
// Default: LastTouchDecider. Config-swappable per install.
```

## What stays (already fresh-correct)

User-key identity with tolerant fallback, applications-only enrollment,
links-backed signed redirects, encrypted network cookie, idempotent
merchant postbacks, engine fraud/commission/upline machinery,
reconciliation-as-proof, email-linked ledger posting for recognition.

## Open decisions

None remaining — all three resolved below.

## Decided

- Balance presentation: derive creator balances from legs at read time
  behind a single `CreatorBalances::for()` seam; legs stay append-only
  with a composite index on `(affiliate_id, status, currency)`. No
  balance table until volume demands it — backfill then is exact and
  repeatable from legs, cut over happens behind the unchanged seam, and
  cached == derived must be proven via reconciliation before switching
  reads.
- Remote merchant catalog sync: merchants push signed snapshots to a
  network endpoint. The network already authenticates merchants for
  postbacks, so push reuses that trust; no polling infrastructure, no
  merchant-side serving requirement.
- Engine notification default: listeners on-by-default when the
  communications package is present. Silence must be a choice (explicit
  opt-out), not an accident.

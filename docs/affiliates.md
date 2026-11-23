---
title: Affiliate Routing Across Packages
status: current
---

# Affiliate routing across package boundaries

Where an affiliate touch travels when it crosses `affiliates`,
`affiliate-network`, `vouchers`, `checkout`/`orders`, `cart`, and the
Filament adapters. Package docs stay canonical; this is the map.

## The two books

- **Merchant books** (`aiarmada/affiliates`): attributions, touchpoints,
  conversions, payouts for one merchant's own program. Conversions carry
  `origin` + `source_ref` provenance.
- **Network books** (`aiarmada/affiliate-network`): marketplace legs. One
  append-only `NetworkConversionLeg` per conversion — revenue, commission, fee,
  payout, pricing tier, event type. Balances derive from posted legs at read
  time; reversals and adjustments post companion legs.

The books meet in exactly one place: **fulfillment**. A posted leg goes to
exactly one payer — the engine fulfils merchant payouts when installed,
otherwise the host records a run against the leg with a non-empty payout
reference. Recording a standalone conversion never claims it has been paid.

## Touch → order → money

1. **Visit.** `?aff=CODE` (engine cookie) or `?anl=slug` (network cookie) lands;
   `TrackAffiliateCookie` / `TrackNetworkLinkCookie` persist it. The network
   middleware also writes a validated click snapshot to the current cart
   metadata. Voucher codes attach via the voucher bridge
   (`default_voucher_code`). Linkless network attribution is also possible via
   an affiliate-assigned promo coupon (`AffiliateOfferCoupon`).
2. **Cart.** `CartBridge` hydrates cookie attribution onto the cart.
3. **Checkout.** Checkout copies the network snapshot into order metadata so
   retries resume without cookies. The merchant records its conversion
   (`RecordAffiliateConversion`, stamped with `origin`/`source_ref`) and — for
   network-referred orders — reports the paid order via
   `NetworkPostbackClient` (idempotent on link/coupon + order reference +
   event type).
4. **Shared checkout.** When engine and network observe the same order, the
   engine posts its conversion and the network posts a *provisional* leg; the
   last-touch decider confirms the winner and supersedes the loser. Every
   decision records `attribution_winner` + `attribution_decision_reason`.

Remote postbacks use a separate decision because the merchant engine and
network share no order event. `CaptureNetworkReferral` stores the
server-observed network click time with the link code; the merchant sends that
time and its engine touch. The network keeps the newer touch, ties go to the
engine, and a null engine touch defaults to the network. If the response says
the network won, the host skips its local `RecordAffiliateConversion` or settles
an existing conversion through `VoidAffiliateConversion::run($conversion, 'network_attribution_won')`.

Merchant reversals and voids emit a merchant-vocabulary event. When both packages
are installed, the network mirrors the first reversal into its ledger; retries
converge on one negated companion leg. Remote merchants get the same symmetry
over HTTP: after voiding locally, `NetworkPostbackClient::voidConversion()`
retracts the network leg through the idempotent void endpoint. Partial refunds
use `adjustConversion()` → `AdjustConversionLeg`, posting a proportional
companion leg.

## Network settlement lifecycle

- **Locking window:** `offer.locking_days` → `site.default_locking_days` →
  `locking.default_days`. A non-zero window writes `pending` legs with
  `locks_at`; `LockMaturedLegs` matures them to `posted`.
- **Caps:** `CheckOfferCaps` enforces daily/monthly/total/per-affiliate
  conversion caps, monthly/total payout caps, site monthly spend, and prepaid
  balance.
- **Statements:** `GenerateMerchantStatement` aggregates posted, unstated
  non-companion legs per site/currency/period; `IssueMerchantStatement` sets
  `issued_at`/`due_at`; `MarkMerchantStatementPaid` settles (and fulfils when
  `require_settled_for_payout`); `VoidMerchantStatement` detaches legs.
- **Outbound postbacks:** `ConfigureCreatorPostback` + `EnqueueCreatorPostbacks`
  deliver signed (`HMAC-SHA256`) events for recorded/locked/reversed/adjusted
  conversions, with `sub1`/`sub2`/`sub3` propagation through
  `PublicHttpUrlGuard`-validated transport.

## The seams (contracts, not classes)

- `affiliates`: `MerchantLedger`, `MerchantIdentity`, `MerchantCatalog`
  — merchant vocabulary only; the engine never names the marketplace.
- `affiliate-network`: `NetworkLedger`, `Fulfillment`,
  `AffiliateIdentityResolver`, `CatalogReader`, `DnsResolver`,
  `SiteContentClient`, `SiteVerificationStrategy`, `CreatorPostbackTransport`
  — bound to engine adapters at boot when the engine is installed, standalone
  otherwise.

## Architecture note (Modular Dune)

`affiliate-network` is a Modular Dune package. Its six features own their writes
and expose only `Api/*` across boundaries:

| Feature | Owns |
|---|---|
| `Sites` | sites, verification, catalog tokens, prepaid balance |
| `Catalog` | offers, categories, creatives, catalog sync, discovery, metrics |
| `Enrollment` | applications, invitations, per-affiliate deals |
| `Attribution` | links, coupons, click/conversion counters |
| `Ledger` | conversion legs, caps, locking, statements, merchant HTTP routes |
| `Postbacks` | creator endpoint config and delivery |

Cross-feature calls use `Api\*`; writes go through `Actions`; reads use
`Queries`. Admins/operators can prove boundaries with
`php packages/affiliate-network/tools/package-check.php --root=packages/affiliate-network`.

## Admin surfaces

- `filament-affiliates`: partners, conversions (origin/source-ref columns,
  reverse action), payouts, programs, ranks, fraud.
- `filament-affiliate-network`:
  - **Sites** — catalog sync, token rotation, verification, settlement/locking
    configuration.
  - **Offers** — fees, tiers, caps, fallback URL/offer, event rates, active
    promotions, restrictions, and six relation tabs: creatives, links, legs
    (adjust/lock/reverse), invitations, deals, coupons.
  - **Categories** and **Applications** (approve/reject/revoke + bulk approve).
  - **Merchant statements** (admin-only: generate/issue/mark paid/void).
  - **Merchant dashboard** (owner-scoped) and network stats/top-offers widgets
    (network-wide, 30s cache).

Proven end to end by `demo/tests/chrome/affiliate-surfaces.mjs`
(`npm run chrome:affiliates` from `demo/`).

## Reference apps

- `rizq` — standalone network marketplace (no engine install).
- `cellmaxx` — merchant store on the engine, reporting to rizq.

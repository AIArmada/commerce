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
  append-only `NetworkConversionLeg` per conversion: revenue, commission,
  fee, payout. Balances derive from posted legs at read time.

The books meet in exactly one place: **fulfillment**. A posted leg goes
to exactly one payer — the engine fulfills merchant payouts when
installed, otherwise the host records the run manually. Mutual exclusion
is structural in the provider, so a leg can never be paid twice.

## Touch → order → money

1. **Visit.** `?aff=CODE` (engine cookie) or `?anl=slug` (network cookie)
   lands; `TrackAffiliateCookie` / `TrackNetworkLinkCookie` persist it.
   Voucher codes attach via the voucher bridge (`default_voucher_code`).
2. **Cart.** `CartBridge` hydrates cookie attribution onto the cart.
3. **Checkout.** The merchant records its conversion
   (`RecordAffiliateConversion`, stamped with `origin`/`source_ref`) and —
   for network-referred orders — reports the paid order via
   `NetworkPostbackClient` (idempotent on link + order reference).
4. **Shared checkout.** When engine and network observe the same order,
   the engine posts its conversion and the network posts a *provisional*
   leg; the last-touch decider confirms the winner and supersedes the
   loser. Every decision is recorded with a reason.

## The seams (contracts, not classes)

- `affiliates`: `MerchantLedger`, `MerchantIdentity`, `MerchantCatalog`
  — merchant vocabulary only; the engine never names the marketplace.
- `affiliate-network`: `NetworkLedger`, `Fulfillment`,
  `AffiliateIdentityResolver` — bound to engine adapters at boot when
  the engine is installed, standalone otherwise.

## Admin surfaces

- `filament-affiliates`: partners, conversions (origin/source-ref
  columns, reverse action), payouts, programs, ranks, fraud.
- `filament-affiliate-network`: sites (sync, token rotation), offers
  (fees, tiers, Legs tab with reverse), categories, applications,
  merchant dashboard.

Proven end to end by `demo/tests/chrome/affiliate-surfaces.mjs`
(`npm run chrome:affiliates` from `demo/`).

## Reference apps

- `rizq` — standalone network marketplace (no engine install).
- `cellmaxx` — merchant store on the engine, reporting to rizq.

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
installed, otherwise the host explicitly records a run against the leg
with a non-empty payout reference. Recording a standalone conversion does
not claim that it has been paid.

## Touch → order → money

1. **Visit.** `?aff=CODE` (engine cookie) or `?anl=slug` (network cookie)
   lands; `TrackAffiliateCookie` / `TrackNetworkLinkCookie` persist it.
   The network middleware also writes a validated click snapshot to the
   current cart metadata.
   Voucher codes attach via the voucher bridge (`default_voucher_code`).
2. **Cart.** `CartBridge` hydrates cookie attribution onto the cart.
3. **Checkout.** Checkout copies the network snapshot into order metadata,
   so retries can resume without the visitor's cookies. The merchant records its conversion
   (`RecordAffiliateConversion`, stamped with `origin`/`source_ref`) and —
   for network-referred orders — reports the paid order via
   `NetworkPostbackClient` (idempotent on link + order reference).
4. **Shared checkout.** When engine and network observe the same order,
   the engine posts its conversion and the network posts a *provisional*
   leg; the last-touch decider confirms the winner and supersedes the
   loser. Every decision is recorded with a reason.

Remote postbacks use a separate decision because the merchant engine and
network have no shared order event. `CaptureNetworkReferral` stores the
server-observed network click time with the link code; the merchant sends that
time and its engine touch to the network. The network keeps the newer touch,
with ties going to the engine and a null engine touch defaulting to the
network. If the response says `winner: network`, the host skips its local
`RecordAffiliateConversion` call or settles an existing conversion through
`VoidAffiliateConversion::run($conversion, 'network_attribution_won')`.

Merchant reversals and voids emit a merchant-vocabulary event. When the
network and engine are installed together, the network mirrors the first
reversal into its ledger; retries converge on one negated companion leg.
Remote merchants get the same symmetry over HTTP: after voiding locally,
`NetworkPostbackClient::voidConversion()` retracts the network leg through
the idempotent postback void endpoint.

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

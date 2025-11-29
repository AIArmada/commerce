# Affiliates Architecture

The affiliates stack introduces referral attribution and commission tracking that can operate independently or alongside the cart & voucher packages.

## Modules

| Path | Purpose |
| --- | --- |
| `packages/affiliates` | Core domain (models, migrations, services, integrations). |
| `packages/filament-affiliates` | Filament plugin for operators (resources, relation managers, widgets). |

### Core package

* `Affiliate` – partner/program definition with status, commission policy, contact info, and optional owner scoping.
* `AffiliateAttribution` – persisted cart session attribution with UTM context, device info, voucher hints, and expiry.
* `AffiliateConversion` – monetized event (order, invoice, top-up) linked to affiliates + commissions.
* `AffiliateService` – operations API handling cart hooks, conversion recording, and DTO projection.
* `CommissionCalculator` – converts basis point or fixed commissions into integer minor units.
* Traits / wrappers – `HasAffiliates`, `CartWithAffiliates`, and `CartManagerWithAffiliates` surface fluent façade methods without hard dependencies.

#### Integrations

* **Cart** – if `aiarmada/cart` is available the manager is proxied so `Cart::attachAffiliate()`, `Cart::recordAffiliateConversion()` etc. are exposed automatically. Metadata is stored under a configurable key so normalized snapshots inherit context.
* **Vouchers** – listens to `VoucherApplied` and inspects voucher metadata (`affiliate_code`, `affiliate.code`, `metadata.affiliate_code`) to auto attach affiliates when a voucher is applied. Falls back to the affiliate `default_voucher_code` when configured, so voucher-driven programs work even without explicit metadata. No runtime cost when vouchers are absent.

#### Events

* `AffiliateAttributed` and `AffiliateConversionRecorded` fire whenever attribution/conversion occurs. Toggle dispatch via `config/affiliates.php`.
* Optional webhook dispatch (`events.dispatch_webhooks`) sends attribution/conversion payloads to configured endpoints.

#### Payouts

`AffiliatePayoutService` batches conversions into `affiliate_payouts` with aggregate totals and references for exports or payment provider pipelines. Conversions store `affiliate_payout_id` for traceability.

#### Links

`AffiliateLinkGenerator` produces signed referral URLs with a configurable parameter (default `aff`), TTL, and signing key to prevent tampering.

### Filament package

* `AffiliateResource` – CRUD + infolist for programs, relation manager for conversions.
* `AffiliateConversionResource` – moderation surface for commissions with deep links into Cart/Voucher resources when those plugins exist.
* `AffiliateStatsWidget` – dashboard summary powered by `AffiliateStatsAggregator`.
* Bridges – `CartBridge` and `VoucherBridge` detect companion plugins at runtime and render contextual buttons without requiring manual configuration.

## Configuration Highlights

* `affiliates.table_names.*` – override if your application uses custom prefixes/schemas.
* `affiliates.owner.*` – plug in your own resolver to scope affiliates per merchant/tenant.
* `affiliates.cart.metadata_key` – key used when persisting cart metadata.
* `affiliates.integrations.vouchers.metadata_keys` – dot-notation paths read from voucher metadata to discover affiliate codes.
* `affiliates.tracking.block_self_referral` – block attribution when the current owner is the same as the affiliate owner.
* `affiliates.tracking.ip_rate_limit.*` – cap attribution attempts per IP within a decay window.
* `affiliates.cookies.require_consent` / `affiliates.cookies.consent_cookie` – require an explicit consent signal before dropping affiliate cookies.
* `affiliates.events.dispatch_webhooks` / `affiliates.webhooks.*` – push attribution/conversion payloads externally.
* `affiliates.links.*` – configure referral link signing.
* `filament-affiliates.integrations.*` – toggle deep linking to other Filament plugins.

## Data Flow

1. **Attribution** – user applies affiliate code or voucher → `AffiliateService` records attribution + cart metadata.
2. **Cart changes** – metadata lives with cart snapshots (via Filament Cart) so reporting remains synchronized.
3. **Conversion** – when an order is finalized call `Cart::recordAffiliateConversion([...])` to write a commission record; widget + Filament UI reflect changes instantly.

This composition keeps each package independent yet interoperable, mirroring the cart/voucher architecture across the monorepo.

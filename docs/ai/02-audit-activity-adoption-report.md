# Audit + Activity Adoption Report

_Last updated: 2026-05-29_

## Scope

This report tracks `HasCommerceAudit` + `LogsCommerceActivity` adoption for Eloquent models under:

- `packages/*/src/Models/*.php`
- excluding `commerce-support` and `filament-*` packages
- only classes containing `extends Model`

## Current Snapshot

- Total Eloquent models scanned: **131**
- Models with both traits: **111**
- Models with activity-only: **7**
- Models with audit-only: **0**
- Models with neither: **13**

## Package Breakdown

| Package | Total | Both | Activity-only | Audit-only | Neither |
|---|---:|---:|---:|---:|---:|
| affiliate-network | 6 | 5 | 0 | 0 | 1 |
| affiliates | 27 | 22 | 0 | 0 | 5 |
| cart | 2 | 2 | 0 | 0 | 0 |
| checkout | 1 | 0 | 1 | 0 | 0 |
| chip | 2 | 1 | 1 | 0 | 0 |
| customers | 5 | 4 | 1 | 0 | 0 |
| docs | 14 | 14 | 0 | 0 | 0 |
| events | 5 | 5 | 0 | 0 | 0 |
| growth | 3 | 3 | 0 | 0 | 0 |
| inventory | 14 | 12 | 0 | 0 | 2 |
| jnt | 5 | 3 | 2 | 0 | 0 |
| orders | 6 | 5 | 1 | 0 | 0 |
| pricing | 3 | 3 | 0 | 0 | 0 |
| products | 10 | 10 | 0 | 0 | 0 |
| promotions | 1 | 1 | 0 | 0 | 0 |
| shipping | 8 | 7 | 1 | 0 | 0 |
| signals | 11 | 6 | 0 | 0 | 5 |
| tax | 4 | 4 | 0 | 0 | 0 |
| vouchers | 4 | 4 | 0 | 0 | 0 |

## Non-`both` Models (Current)

### Neither (intentional)

- `affiliate-network.AffiliateOfferLink`
- `affiliates.AffiliateAttribution`
- `affiliates.AffiliateConversion`
- `affiliates.AffiliateDailyStat`
- `affiliates.AffiliateSupportMessage`
- `affiliates.AffiliateTouchpoint`
- `inventory.InventoryDemandHistory`
- `inventory.InventorySerialHistory`
- `signals.SignalAlertLog`
- `signals.SignalDailyMetric`
- `signals.SignalEvent`
- `signals.SignalIdentity`
- `signals.SignalSession`

### Activity-only (intentional)

- `checkout.CheckoutSession`
- `chip.ChipIntegerModel`
- `customers.CustomerNote`
- `jnt.JntTrackingEvent`
- `jnt.JntWebhookLog`
- `orders.OrderNote`
- `shipping.ShipmentEvent`

## Intent Notes

The remaining non-`both` set is intentional and falls into one or more of these categories:

1. **High-volume telemetry/event streams** where full auditing would be noisy and expensive.
2. **Free-text/sensitive-content surfaces** where full field auditing is not preferred.
3. **UUID/int-key compatibility caution paths** where activity logging remains safer than full auditing.

## Validation Notes

- Formatting: `vendor/bin/pint --dirty --format agent` ✅
- Targeted static analysis over touched model/service files: `phpstan --level=6` ✅
- Parallel Pest runs encountered an existing ParaTest worker crash in this environment (`Exit Code 2: Misuse of shell builtins`), so coverage verification relied on static validation + package-level checks where runnable.

## Operational Recommendation

Treat this rollout as complete for the current policy boundary:

- Keep current intentional exclusions as-is.
- Revisit exclusions only if/when compliance policy changes (especially for telemetry and free-text models).

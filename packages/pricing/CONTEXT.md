---
title: Pricing Context
package: pricing
status: current
surface: domain
family: catalog-and-identity
keywords:
  - price
  - price-list
  - tier
  - resolution
---

# Pricing Context

## Snapshot
- Composer: `aiarmada/pricing`
- Role: Price lists, tiers, priority price-resolution engine.
- Triggers: price, price-list, tier, resolution
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-pricing`, `products`, `promotions`, `customers`
- Paired: `filament-pricing` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-pricing/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-pricing`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Price resolution or list/tier management.
- Skip when: Discount campaigns — see promotions; vouchers — see vouchers.
- Owner/security: Owner-scoped (all 3; pricing.features.owner).

## Key surfaces
- Models: `Price`, `PriceList`, `PriceTier`
- Actions/Services: `Actions/ApplyPromotionalAdjustment`, `Actions/FormatPriceForDisplay`, `Actions/ResolveBasePrice`, `Actions/ResolveTierPrice`, `Services/PriceCalculator`, `Support/CustomerPriceResolver`, `Support/PricingIntegrationRegistrar`, `Support/PromotionalPriceResolver`
- Config `pricing.php`: `database`, `tables`, `prices`, `price_lists`, `price_tiers`, `defaults`, `currency`, `features`, `promotional`, `enabled`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-models.md`, `06-multitenancy.md`, `07-contracts-dtos.md`

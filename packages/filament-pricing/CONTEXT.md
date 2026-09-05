---
title: Filament Pricing Context
package: filament-pricing
status: current
surface: filament
family: catalog-and-identity
keywords:
  - filament
  - pricing-ui
  - simulator
---

# Filament Pricing Context

## Snapshot
- Composer: `aiarmada/filament-pricing`
- Role: Filament pricing admin: price lists, simulator, settings pages.
- Triggers: filament, pricing-ui, simulator
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `pricing`, `promotions`, `filament-promotions`
- Paired: `pricing` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../pricing/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `pricing`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `pricing` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Pricing admin/simulator UI.
- Skip when: Price resolution — see pricing.
- Owner/security: Owner-aware simulator + relation managers.

## Key surfaces
- Resources: `PriceListResource`
- Config `filament-pricing.php`: `navigation`, `group`, `settings_group`, `resources`, `navigation_sort`, `price_lists`, `pages`, `navigation_sort`, `settings`, `price_simulator`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-resources.md`, `06-pages-widgets.md`, `07-multitenancy.md`

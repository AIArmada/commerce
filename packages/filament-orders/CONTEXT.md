---
title: Filament Orders Context
package: filament-orders
status: current
surface: filament
family: checkout-flow
keywords:
  - filament
  - orders-ui
  - timeline
  - fulfillment
---

# Filament Orders Context

## Snapshot
- Composer: `aiarmada/filament-orders`
- Role: Filament admin for orders: timelines, fulfillment page, invoice downloads.
- Triggers: filament, orders-ui, timeline, fulfillment
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `orders`, `checkout`
- Paired: `orders` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../orders/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `orders`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `orders` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Order admin UI.
- Skip when: State transitions — see orders.
- Owner/security: Delegates to orders scope.

## Key surfaces
- Resources: `OrderResource`
- Actions/Services: `Support/FilamentOrdersCache`
- Config `filament-orders.php`: `navigation`, `group`, `sort`, `pages`, `timeline`, `fulfillment`, `navigation_sort`, `fulfillment`, `timeline`, `payment_gateways`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-customization.md`

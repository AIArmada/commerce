---
title: Filament Cart Context
package: filament-cart
status: current
surface: filament
family: checkout-flow
keywords:
  - filament
  - cart-admin
  - abandonment
  - monitoring
---

# Filament Cart Context

## Snapshot
- Composer: `aiarmada/filament-cart`
- Role: Filament v5 admin adapter for cart snapshots, conditions, live monitoring, and abandonment operations.
- Triggers: filament, cart-admin, abandonment, monitoring
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `cart`, `signals`, `filament-signals`
- Paired: `cart` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../cart/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `cart`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `cart` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Cart admin/monitoring UI.
- Skip when: Cart math — see cart.
- Owner/security: Read-model with owner trait (mirrors core).

## Key surfaces
- Models: none; resources consume `AIArmada\\Cart\\Snapshots\\CartSnapshot`, `CartSnapshotItem`, and `CartSnapshotCondition`
- Actions/Services: `Actions/ApplyConditionAction`, `Actions/RemoveConditionAction`, `Services/CartDownloadService`
- Resources: `CartItemResource`, `CartResource`, `ConditionResource`
- Config `filament-cart.php`: `navigation`, `resources`, `pages`, `polling_interval`, `features`, `widgets`, `notifications`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-synchronization.md`, `06-widgets.md`, `07-analytics.md`, `08-recovery.md`, `09-monitoring.md`, `10-multitenancy.md`

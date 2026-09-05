---
title: Filament Customers Context
package: filament-customers
status: current
surface: filament
family: catalog-and-identity
keywords:
  - filament
  - customers-ui
  - merge
  - segments
---

# Filament Customers Context

## Snapshot
- Composer: `aiarmada/filament-customers`
- Role: Filament admin for customers/segments incl. merge, rebuild, validation pages.
- Triggers: filament, customers-ui, merge, segments
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `customers`, `filament-authz`
- Paired: `customers` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../customers/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `customers`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `customers` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Customer admin UI.
- Skip when: Segmentation math — see customers.
- Owner/security: getEloquentQuery + ensureRecordOwnerScope.

## Key surfaces
- Resources: `CustomerResource`, `SegmentResource`
- Actions/Services: `Actions/MergeCustomersAction`
- Config `filament-customers.php`: `navigation`, `group`, `features`, `merge_customers`, `segment_rebuild`, `address_validation`, `resources`, `navigation_sort`, `customers`, `segments`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-widgets.md`

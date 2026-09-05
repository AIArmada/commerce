---
title: Filament Promotions Context
package: filament-promotions
status: current
surface: filament
family: growth-and-incentives
keywords:
  - filament
  - promotions-ui
---

# Filament Promotions Context

## Snapshot
- Composer: `aiarmada/filament-promotions`
- Role: Filament admin for promotions + issue-vouchers actions.
- Triggers: filament, promotions-ui
- Search first: `src/Resources, src/Widgets, config, docs`
- Related: `promotions`, `filament-pricing`
- Paired: `promotions` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../promotions/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `promotions`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `promotions` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Promotion admin UI.
- Skip when: Discount math — see promotions; pricing UI — see filament-pricing.
- Owner/security: Filament adapter.

## Key surfaces
- Resources: `PromotionResource`
- Actions/Services: `Actions/IssuePromotionVouchersAction`, `Actions/IssuePromotionVouchersFromListAction`
- Config `filament-promotions.php`: `navigation`, `group`, `resources`, `navigation_sort`, `promotions`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

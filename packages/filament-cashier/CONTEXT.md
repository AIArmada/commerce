---
title: Filament Cashier Context
package: filament-cashier
status: current
surface: filament
family: payments-and-documents
keywords:
  - filament
  - billing-ui
  - mrr
  - gateway-compare
---

# Filament Cashier Context

## Snapshot
- Composer: `aiarmada/filament-cashier`
- Role: Unified Filament billing UI across Stripe + CHIP.
- Triggers: filament, billing-ui, mrr, gateway-compare
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `cashier`, `cashier-chip`, `filament-cashier-chip`
- Paired: `cashier` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../cashier/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `cashier`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `cashier` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Billing dashboards/portals.
- Skip when: Gateway logic — see cashier.
- Owner/security: Filament adapter.

## Key surfaces
- Resources: `UnifiedInvoiceResource`, `UnifiedSubscriptionResource`
- Actions/Services: `Support/CustomerSubscriptionsQuery`
- Config `filament-cashier.php`: `navigation`, `group`, `sort`, `tables`, `polling_interval`, `date_format`, `features`, `dashboard`, `subscriptions`, `invoices`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

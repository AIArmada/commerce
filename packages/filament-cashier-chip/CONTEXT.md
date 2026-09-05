---
title: Filament Cashier Chip Context
package: filament-cashier-chip
status: current
surface: filament
family: payments-and-documents
keywords:
  - filament
  - chip-portal
  - subscriptions-ui
---

# Filament Cashier Chip Context

## Snapshot
- Composer: `aiarmada/filament-cashier-chip`
- Role: Filament admin + customer portal for CHIP subscriptions.
- Triggers: filament, chip-portal, subscriptions-ui
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `cashier-chip`, `filament-cashier`, `chip`
- Paired: `cashier-chip` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../cashier-chip/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `cashier-chip`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `cashier-chip` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: CHIP subscription admin/portal.
- Skip when: Renewal logic — see cashier-chip.
- Owner/security: Filament adapter.

## Key surfaces
- Resources: `BaseCashierChipResource`, `CustomerResource`, `InvoiceResource`, `SubscriptionResource`
- Actions/Services: `Support/FormatsSubscriptionStatus`
- Config `filament-cashier-chip.php`: `navigation`, `group`, `badge_color`, `tables`, `polling_interval`, `date_format`, `amount_precision`, `features`, `subscriptions`, `customers`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-billing-portal.md`, `06-widgets.md`

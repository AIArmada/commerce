---
title: Filament Chip Context
package: filament-chip
status: current
surface: filament
family: payments-and-documents
keywords:
  - filament
  - chip-admin
  - purchases-ui
---

# Filament Chip Context

## Snapshot
- Composer: `aiarmada/filament-chip`
- Role: Filament explorer for CHIP purchases/clients (+ optional Send surfaces).
- Triggers: filament, chip-admin, purchases-ui
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `chip`, `cashier-chip`, `filament-cashier-chip`
- Paired: `chip` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../chip/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `chip`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `chip` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: CHIP transaction visibility.
- Skip when: Gateway calls — see chip.
- Owner/security: Filament adapter.

## Key surfaces
- Resources: `AuditLogResource`, `BankAccountResource`, `BaseChipResource`, `ClientResource`, `CompanyStatementResource`, `ComplianceReportResource`, `FraudReviewResource`, `PaymentLinkResource`, `PaymentResource`, `PurchaseResource`
- Actions/Services: `Actions/PurchaseExporter`, `Actions/SendInstructionExporter`
- Config `filament-chip.php`: `navigation`, `group`, `badge_color`, `polling_interval`, `tables`, `created_on_format`, `updated_on_format`, `amount_precision`, `default_currency`, `features`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-pages-widgets.md`, `index.md`

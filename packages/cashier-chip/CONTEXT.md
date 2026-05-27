---
title: Cashier CHIP Context
package: cashier-chip
status: current
surface: gateway-billing
family: payments-and-documents
---

# Cashier CHIP Context

## Snapshot
- Composer: `aiarmada/cashier-chip`
- Role: Cashier-style recurring billing and billable-model support on top of CHIP.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `config`, `docs`
- Related: `filament-cashier-chip`, `cashier`, `chip`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-cashier-chip/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns CHIP-backed recurring billing behavior and billable-model integration.
- Audit `cashier`, `chip`, and `filament-cashier-chip` when subscription behavior changes.
- Update `docs/*.md` in the same pass when public behavior or config changes.

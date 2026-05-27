---
title: Cashier Context
package: cashier
status: current
surface: billing-abstraction
family: payments-and-documents
---

# Cashier Context

## Snapshot
- Composer: `aiarmada/cashier`
- Role: Unified multi-gateway billing abstraction across Stripe and CHIP-style providers.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `config`, `docs`
- Related: `filament-cashier`, `cashier-chip`, `chip`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-cashier/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns gateway-neutral billing flows and shared billing contracts.
- Audit gateway adapters and `filament-cashier` when billing behavior changes.
- Update `docs/*.md` in the same pass when public behavior or config changes.

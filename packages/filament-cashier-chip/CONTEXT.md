---
title: Filament Cashier CHIP Context
package: filament-cashier-chip
status: current
surface: filament
family: payments-and-documents
---

# Filament Cashier CHIP Context

## Snapshot
- Composer: `aiarmada/filament-cashier-chip`
- Role: Filament admin UI and billing portal for CHIP subscription billing.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `cashier-chip`, `filament-cashier`, `chip`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../cashier-chip/CONTEXT.md` when billing behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin, panel, or portal setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, panel/plugin glue, and billing portal UI behavior.
- Keep CHIP-backed subscription rules, persistence, and billable-model logic in `cashier-chip`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

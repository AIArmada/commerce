---
title: Filament CHIP Context
package: filament-chip
status: current
surface: filament
family: payments-and-documents
---

# Filament CHIP Context

## Snapshot
- Composer: `aiarmada/filament-chip`
- Role: Filament admin UI for CHIP purchases, clients, analytics, and transaction visibility.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `chip`, `cashier-chip`, `filament-cashier-chip`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../chip/CONTEXT.md` when gateway behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep gateway rules, persistence, and webhook processing in `chip`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

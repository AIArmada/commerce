---
title: Filament Vouchers Context
package: filament-vouchers
status: current
surface: filament
family: growth-and-incentives
---

# Filament Vouchers Context

## Snapshot
- Composer: `aiarmada/filament-vouchers`
- Role: Filament admin UI for vouchers, usage, wallets, and voucher settings.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `vouchers`, `filament-cart`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../vouchers/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep domain rules, persistence, and state transitions in `vouchers`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

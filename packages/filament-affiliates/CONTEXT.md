---
title: Filament Affiliates Context
package: filament-affiliates
status: current
surface: filament
family: growth-and-incentives
---

# Filament Affiliates Context

## Snapshot
- Composer: `aiarmada/filament-affiliates`
- Role: Filament admin UI and affiliate portal for affiliates.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `src/Panel*`, `config`, `docs`
- Related: `affiliates`, `affiliate-network`, `filament-affiliate-network`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../affiliates/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin, panel, or portal setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, panel/plugin glue, and portal UI behavior.
- Keep domain rules, persistence, and state transitions in `affiliates`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

## Owner scoping
- `includeGlobal: false` is the default. Global affiliate rows are intentionally not visible in the Filament panel or affiliate portal.
- `AffiliateResource::getEloquentQuery()` uses conditional owner-scoping: when `config('affiliates.owner.enabled')` is false, returns unscoped query; when enabled, calls `->forOwner()` without includeGlobal.
- OwnerWriteGuard is applied on write paths across conversion, payout, link, and support ticket resources.

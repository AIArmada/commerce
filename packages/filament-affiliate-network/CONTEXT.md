---
title: Filament Affiliate Network Context
package: filament-affiliate-network
status: current
surface: filament
family: growth-and-incentives
---

# Filament Affiliate Network Context

## Snapshot
- Composer: `aiarmada/filament-affiliate-network`
- Role: Filament admin UI and marketplace pages for the affiliate network.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `affiliate-network`, `filament-affiliates`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../affiliate-network/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Design: global-admin-only
This package is designed as a **global admin surface only**. All 4 resources bypass owner scoping in `getEloquentQuery()` — they show network-wide data across all tenants.
- `$tenantOwnershipRelationshipName` is explicitly set to `null` on all resources, disabling Filament's built-in multi-tenancy.
- Resources use `withoutOwnerScope()` / `withoutGlobalScope('owner_via_site')` / `withoutGlobalScope('owner_via_affiliate')` to bypass per-tenant scoping.
- This is intentional: the marketplace and admin dashboard must operate across all tenant data.
- If per-tenant views are needed in the future, create a separate portal resource with proper scoping.

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep marketplace rules, persistence, and state transitions in `affiliate-network`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

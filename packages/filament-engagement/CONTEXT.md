---
title: Filament Engagement Context
package: filament-engagement
status: current
surface: filament
family: growth-and-incentives
---

# Filament Engagement Context

## Snapshot
- Composer: `aiarmada/filament-engagement`
- Role: Filament admin UI for engagement records, widgets, relation managers, and reusable admin actions.
- Search first: `src/Resources`, `src/RelationManagers`, `src/Widgets`, `src/Actions`, `src/Support`, `config`, `docs`
- Related: `engagement`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../engagement/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, widgets, relation managers, pages, tables, forms, and reusable admin actions.
- Keep engagement domain logic, persistence, and business rules in `engagement`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.
- Use owner-safe queries and do not rely on Filament tenancy as a security boundary.
- Update `docs/*.md` in the same pass when public behavior or config changes.

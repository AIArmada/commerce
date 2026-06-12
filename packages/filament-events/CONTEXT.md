---
title: Filament Events Context
package: filament-events
status: current
surface: filament
family: analytics-and-events
---

# Filament Events Context

## Snapshot
- Composer: `aiarmada/filament-events`
- Role: Filament admin UI for events, occurrences, sessions, venues, registrations, ticket types, attendance, check-in, and moderation workflows.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/RelationManagers`, `src/Actions`, `src/Support`, `config`, `docs`
- Related: `events`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../events/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, relation managers, forms, tables, and admin-only workflow actions.
- Keep event-domain rules, persistence, and lifecycle workflows in `events`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.
- Use owner-safe resource queries and do not rely on Filament tenancy as a security boundary.
- Update `docs/*.md` in the same pass when public behavior or config changes.

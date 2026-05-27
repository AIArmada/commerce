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
- Role: Filament admin UI for event series, events, occurrences, venues, and registrations.
- Search first: `src/Resources`, `src/Pages`, `src/Widgets`, `src/Actions`, `config`, `docs`
- Related: `events`, `filament-authz`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../events/CONTEXT.md` when domain behavior or persistence changes are involved
6. `docs/02-installation.md` when plugin or panel setup changes are involved

## Guardrails
- Owns Filament resources, pages, widgets, tables, forms, and panel/plugin glue.
- Keep event-domain rules, persistence, and state transitions in `events`.
- Revalidate submitted IDs server-side; UI scoping is not authorization.

---
title: Events Context
package: events
status: current
surface: domain
family: analytics-and-events
---

# Events Context

## Snapshot
- Composer: `aiarmada/events`
- Role: Event definitions, scheduling, venues, registrations, check-in, attendance, and change workflows, with ticketing and seating integrated through sibling packages.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Support`, `src/Resolvers`, `src/Listeners`, `src/Events`, `src/Console/Commands`, `src/Steps`, `src/Data`, `config`, `docs`
- Related: `ticketing`, `seating`, `filament-events`, `commerce-support`, `engagement`, `products`, `customers`, `orders`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-events/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns event-domain models, actions, services, resolvers, listeners, events, console commands, and persistence rules.
- Keep ticket types, passes, pass transfers, seat layouts, seat holds, and seat allocations in `ticketing` / `seating`; only keep event-scoped orchestration here.
- Keep Filament resources, pages, widgets, relation managers, and admin-only workflow actions in `filament-events`.
- Preserve owner-aware queries, explicit owner context, and polymorphic integrations.
- Prefer actions and workflow services for orchestration; keep models and listeners thin.
- Update `docs/*.md` in the same pass when public behavior or config changes.

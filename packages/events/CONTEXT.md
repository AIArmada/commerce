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
- Role: Event series, venues, occurrences, attendee registrations, and registration lifecycle rules.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `config`, `docs`
- Related: `filament-events`, `products`, `customers`, `orders`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-events/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-events`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

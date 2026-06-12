---
title: Engagement Context
package: engagement
status: current
surface: domain
family: growth-and-incentives
---

# Engagement Context

## Snapshot
- Composer: `aiarmada/engagement`
- Role: Polymorphic engagement interactions, reminders, subscriptions, shares, and counters.
- Search first: `src/Models`, `src/Traits`, `src/Contracts`, `src/Services`, `src/Support`, `src/Integrations`, `src/Events`, `src/Notifications`, `src/Console/Commands`, `database`, `config`, `docs`
- Related: `filament-engagement`, `commerce-support`, `events`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-engagement/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns engagement models, traits, contracts, services, events, notifications, commands, and persistence rules.
- Keep Filament resources, widgets, relation managers, and admin actions in `filament-engagement`.
- Preserve polymorphic intent tracking and lifecycle semantics; do not collapse distinct engagement types into one model or state flow.
- Use owner-safe queries and explicit owner context when reading or writing tenant-owned data.
- Update `docs/*.md` in the same pass when public behavior or config changes.

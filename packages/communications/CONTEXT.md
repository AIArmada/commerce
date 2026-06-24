---
title: Communications Context
package: aiarmada/communications
status: current
surface: domain
family: communications
---

# Communications Context

## Snapshot

- Composer: `aiarmada/communications`
- Role: Outbound, inbound, internal, and inbox communication recording, delivery tracking, template management, preference and suppression enforcement, and Laravel Notifications integration.
- Search first: `src/Models`, `src/Actions`, `src/Contracts`, `src/Data`, `src/Enums`, `src/Events`, `src/Http`, `src/Services`, `src/Traits`, `src/Console`, `resources/views`, `config`, `docs`
- Related: `filament-communications`, `commerce-support`, `contacting`

## Read next

1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-communications/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails

- Owns communication-domain models, actions, services, resolvers, listeners, events, console commands, and persistence rules.
- Complements Laravel Notifications — does not replace it, the queue, or Horizon.
- Keeps Filament resources, pages, widgets, relation managers, and admin-only workflow actions in `filament-communications`.
- Preserves owner-aware queries, explicit owner context, inbox scoping, and polymorphic integrations.
- Prefers actions and workflow services for orchestration; keeps models and listeners thin.
- Updates `docs/*.md` in the same pass when public behavior or config changes.

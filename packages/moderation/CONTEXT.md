---
title: Moderation Context
package: moderation
status: current
surface: domain
family: governance-and-safety
keywords:
  - block
  - ban
  - moderation
  - report
---

# Moderation Context

## Snapshot
- Composer: `aiarmada/moderation`
- Role: Blocking, bans, and moderation-action log (polymorphic).
- Triggers: block, ban, moderation, report
- Search first: `src/Models, src/Actions, config, docs`
- Related: `events`, `commerce-support`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. related package contexts when the change crosses boundaries
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Blocking users/models or logging moderation.
- Skip when: No admin UI ships (no filament-moderation).
- Owner/security: Owner-scoped (both models).

## Key surfaces
- Models: `Block`, `ModerationAction`
- Actions/Services: `Actions/BlockEntityAction`, `Actions/RecordModerationAction`
- Config `moderation.php`: `database`, `owner`, and `defaults`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

---
title: Moderation Context
package: aiarmada/moderation
status: current
surface: domain
family: governance-and-safety
---

# Moderation Context

## Snapshot

- Composer: `aiarmada/moderation`
- Role: Generic moderation, blocking, and moderation-action recording for Laravel models
- Search first: `src/Models`, `src/Actions`, `src/Traits`, `src/Enums`, `config`, `docs`
- Related: `commerce-support`, `events`

## Read next

1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `docs/02-installation.md` when installation or publishing changes are involved

## Guardrails

- Owns block records, moderation-action records, and the traits/actions that create them.
- Keeps moderation rules out of UI packages and out of unrelated domain packages.
- Validates owner-scoped models with `commerce-support` guards, but still supports global models explicitly.
- Uses UUID primary keys, no DB foreign keys, no DB cascades, and no soft deletes.
- Updates `docs/*.md` in the same pass when public behavior or config changes.

---
title: Filament Engagement Context
package: filament-engagement
status: current
surface: filament
family: growth-and-incentives
keywords:
  - filament
  - engagement-ui
---

# Filament Engagement Context

## Snapshot
- Composer: `aiarmada/filament-engagement`
- Role: Filament admin for follows/bookmarks/RSVPs/reactions/subs/reminders + actions.
- Triggers: filament, engagement-ui
- Search first: `src/Resources, src/Actions, config, docs`
- Related: `engagement`, `commerce-support`
- Paired: `engagement` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../engagement/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `engagement`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `engagement` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Engagement admin UI.
- Skip when: Engagement managers — see engagement.
- Owner/security: OwnerUiScope in queries.

## Key surfaces
- Resources: `BookmarkCollectionResource`, `BookmarkResource`, `FollowResource`, `ReactionResource`, `ReminderResource`, `ResponseResource`, `SubscriptionResource`
- Actions/Services: `Actions/BookmarkAction`, `Actions/FollowAction`, `Actions/ReactAction`, `Actions/RemoveBookmarkAction`, `Actions/RespondAction`, `Actions/SetReminderAction`, `Actions/SubscribeAction`, `Actions/UnfollowAction`
- Config `filament-engagement.php`: `navigation`, `group`, `resources`, `enabled`, `follow`, `bookmark`, `collection`, `response`, `reaction`, `subscription`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

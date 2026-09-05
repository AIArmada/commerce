---
title: Engagement Context
package: engagement
status: current
surface: domain
family: growth-and-incentives
keywords:
  - follow
  - bookmark
  - like
  - reaction
  - subscribe
  - reminder
  - share
---

# Engagement Context

## Snapshot
- Composer: `aiarmada/engagement`
- Role: Polymorphic engagement: follows, bookmarks, likes/reactions, subscriptions, reminders, shares, counters.
- Triggers: follow, bookmark, like, reaction, subscribe, reminder, share
- Search first: `src/Models, src/Services, config, docs`
- Related: `commerce-support`, `filament-engagement`, `events`
- Paired: `filament-engagement` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-engagement/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-engagement`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Social-style interactions on any entity.
- Skip when: Surveys/testimonials — see feedback; analytics ingestion — see signals.
- Owner/security: Owner-scoped (all models; engagement.owner).

## Key surfaces
- Models: `Bookmark`, `BookmarkCollection`, `BookmarkCollectionItem`, `EngagementCounter`, `Follow`, `Reaction`, `Reminder`, `Response`, `Share`, `Subscription`
- Actions/Services: `Services/DefaultEngagementCounterService`, `Services/DefaultEngagementManager`, `Services/DefaultEngagementPolicyResolver`, `Services/DefaultEngagementStateResolver`, `Services/DefaultReminderManager`, `Services/DefaultShareUrlGenerator`, `Services/DefaultSubscriptionManager`, `Support/ModelResolver`
- Config `engagement.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `follows`, `bookmarks`, `bookmark_collections`, `bookmark_collection_items`, `responses`, `reactions`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package

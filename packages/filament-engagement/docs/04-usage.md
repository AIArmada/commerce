---
title: Usage
---

## Resources

### Follows

`FollowResource` provides a list view of all follow records.

**Table columns:** follower_type (badge), follower_id, followable_type (badge), followable_id, status (badge — color-coded: active=success, muted=warning, unfollowed=danger, blocked=gray), notification_level (badge), followed_at (sortable), unfollowed_at.

**Filters:** status, follower_type, followable_type.

**Row actions:**
- **Mute** — sets status to `muted` (visible on active follows)
- **Unmute** — sets status back to `active` (visible on muted follows)
- **Unfollow** — sets status to `unfollowed` (visible on active follows)

**Bulk actions:** Mute, Unfollow.

### Bookmarks

`BookmarkResource` provides list and view for bookmark records.

**Table columns:** bookmarker_type (badge), bookmarkable_type (badge), status (badge), notes, bookmarked_at (sortable).

**Pages:** List, View.

### Bookmark Collections

`BookmarkCollectionResource` provides full CRUD for bookmark collections.

**Table columns:** name, slug, visibility (badge), status (badge), is_default, is_system (badge), items_count.

**Pages:** List, Create, Edit.

### Responses

`ResponseResource` provides list and view for response/RSVP records.

**Table columns:** responder_type (badge), respondable_type (badge), response_type (badge), visibility (badge), status (badge).

**Pages:** List, View.

### Reactions

`ReactionResource` provides list and view for reaction records.

**Table columns:** reactor_type (badge), reactable_type (badge), reaction_type (badge), status (badge), reacted_at (sortable).

**Pages:** List, View.

### Subscriptions

`SubscriptionResource` provides list and view for subscription records.

**Table columns:** subscriber_type (badge), subscribable_type (badge), subscription_type (badge), status (badge), criteria.

**Pages:** List, View.

### Reminders

`ReminderResource` provides list and view for reminder records.

**Table columns:** remindable_type (badge), recipient_type (badge), reminder_type (badge), remind_at (sortable), status (badge), channels.

**Pages:** List, View.

## Reusable Actions

These actions can be added to any Filament table to enable engagement features on the fly:

```php
use AIArmada\FilamentEngagement\Actions\FollowAction;
use AIArmada\FilamentEngagement\Actions\BookmarkAction;
use AIArmada\FilamentEngagement\Actions\ReactAction;
use AIArmada\FilamentEngagement\Actions\RespondAction;

// On any resource table:
Tables\Actions\ActionGroup::make([
    FollowAction::make(),
    BookmarkAction::make(),
    ReactAction::make()->reactionType('like'),
    RespondAction::make()->responseType('going'),
]),
```

Available actions:
- `FollowAction::make()` — records a follow by the current user
- `UnfollowAction::make()` — removes an existing follow
- `BookmarkAction::make()` — bookmarks the record
- `RemoveBookmarkAction::make()` — removes a bookmark
- `ReactAction::make()->reactionType('like')` — records a reaction with configurable type
- `RespondAction::make()->responseType('going')` — records an RSVP with configurable type
- `SubscribeAction::make()` — subscribes to updates on the record
- `SetReminderAction::make()` — opens a form to set a reminder with offset

## Relation Managers

Relation managers can be added to any resource to show engagement data in context:

```php
use AIArmada\FilamentEngagement\RelationManagers\FollowersRelationManager;

public static function getRelations(): array
{
    return [
        FollowersRelationManager::class,
    ];
}
```

Available relation managers:
- `FollowersRelationManager` — table of follows on this model (relationship: `follows`)
- `BookmarksRelationManager` — bookmarks on this model (relationship: `bookmarks`)
- `ReactionsRelationManager` — reactions on this model (relationship: `reactions`)
- `ResponsesRelationManager` — responses on this model (relationship: `responses`)
- `SubscriptionsRelationManager` — subscriptions on this model (relationship: `subscriptions`)
- `RemindersRelationManager` — reminders on this model (relationship: `reminders`)

## Widget

The `EngagementOverviewWidget` shows 5 stat cards:

- **Active Follows** — total follows with `active` status
- **Active Bookmarks** — total bookmarks with `active` status
- **Active Responses** — total responses with `active` status
- **Active Subscriptions** — total subscriptions with `active` status
- **Due Reminders** — reminders with `pending` or `scheduled` status

## Disabling resources

Individual resources can be disabled via config:

```php
'resources' => [
    'enabled' => [
        'reminder' => false,
    ],
],
```

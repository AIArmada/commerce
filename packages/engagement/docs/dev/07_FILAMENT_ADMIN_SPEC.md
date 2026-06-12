# 07 — Filament Admin Spec

The Interactions package should include an optional Filament admin counterpart.

It must allow administrators to inspect, filter, troubleshoot, moderate, and manage interactions.

## Navigation groups

```text
Interactions
- Follows
- Bookmarks
- Bookmark Collections
- Responses
- Reactions
- Subscriptions
- Reminders
- Counters optional
```

## Resources

### FollowResource

Features:

- List follows.
- Filter by status, followable type, follower type, notification level, followed date.
- View follower and followable morph labels.
- Actions: mute, unmute, mark active, mark unfollowed, block.
- Bulk actions: mute, unfollow, export.

Columns:

```text
follower
followable
status
notification_level
followed_at
muted_at
unfollowed_at
updated_at
```

### BookmarkResource

Features:

- List bookmarks.
- Filter by bookmarkable type, bookmarker type, status, bookmarked date.
- Actions: restore active, remove, archive.
- Relation manager for collection items.

Columns:

```text
bookmarker
bookmarkable
status
bookmarked_at
removed_at
archived_at
notes
```

### BookmarkCollectionResource

Features:

- CRUD collections.
- Manage collection items.
- Filter by owner type, visibility, status.
- Reorder items.

Pages:

```text
ListBookmarkCollections
CreateBookmarkCollection
EditBookmarkCollection
ViewBookmarkCollection
ManageCollectionItems
```

### ResponseResource

Features:

- List explicit responses.
- Filter by response type, respondable type, status, visibility.
- Useful for event “going/interested/maybe” troubleshooting.
- Actions: cancel, restore active, change response type.

Columns:

```text
responder
respondable
response_type
status
visibility
responded_at
changed_at
cancelled_at
```

### ReactionResource

Features:

- List reactions.
- Filter by reaction type, reactable type, status.
- Actions: remove, restore active.

Columns:

```text
reactor
reactable
reaction_type
status
reacted_at
removed_at
```

### SubscriptionResource

Features:

- List subscriptions.
- Filter by subscription type, subscribable type, status, notification level.
- View criteria JSON.
- Actions: mute, unmute, unsubscribe, expire.
- Test match action against a selected subject if Events package is installed.

Columns:

```text
subscriber
subscribable
subscription_type
status
notification_level
subscribed_at
muted_at
unsubscribed_at
expires_at
```

### ReminderResource

Features:

- List reminders.
- Filter by reminder type, remindable type, status, channel, due date.
- Actions: send now, cancel, mark sent, retry failed.
- Integration with Laravel Notifications.

Columns:

```text
recipient
remindable
reminder_type
status
remind_at
offset_minutes
channel
scheduled_at
sent_at
failed_at
cancelled_at
```

### EngagementCounterResource optional

Features:

- List derived counters.
- Recalculate counter action.
- Recalculate all counters action.

## Dashboard widgets

```text
Total active follows
Total active bookmarks
Responses by type
Reactions by type
Active subscriptions
Due reminders
Failed reminders
Top followed subjects
Top bookmarked subjects
Top responded subjects
```

## Relation managers for external resources

The package should expose reusable Filament relation managers/components that host packages can attach to their resources:

```text
FollowersRelationManager
BookmarksRelationManager
ResponsesRelationManager
ReactionsRelationManager
SubscriptionsRelationManager
RemindersRelationManager
```

Events package can attach these to:

```text
EventResource
EventOccurrenceResource
EventSessionResource
SpeakerResource if domain provides it
MasjidResource if domain provides it
```

## Filament actions for external pages

Reusable actions:

```text
FollowAction
UnfollowAction
BookmarkAction
RemoveBookmarkAction
RespondAction
ReactAction
SubscribeAction
SetReminderAction
```

These must call service contracts, not directly create rows.

## Admin safety

- Admin actions must respect authorization policies.
- Admin should not accidentally create duplicate active interactions.
- Dangerous state changes should require confirmation.
- JSON criteria/metadata should be editable only with validation.
- No hardcoded Events dependency in Interactions Filament resources.
- Events-specific widgets should appear only if Events package is installed.

## Checklist

- [x] Create all Filament resources. (7 resources: Follow, Bookmark, BookmarkCollection, Response, Reaction, Subscription, Reminder)
- [x] Add filters for status/type/date. (select filters on each resource)
- [x] Add bulk actions where safe. (bulk mute/unfollow on FollowResource)
- [x] Add relation managers. (6 reusable: Followers, Bookmarks, Responses, Reactions, Subscriptions, Reminders)
- [x] Add dashboard widgets. (EngagementOverviewWidget with 5 stat cards)
- [x] Add package config to enable/disable Filament registration. (config/engagement.php filament section)
- [x] Ensure resources work without Events package installed. (no Events dependency)
- [x] Ensure Events package can optionally register interaction relation managers. (relation managers reusable by any package)

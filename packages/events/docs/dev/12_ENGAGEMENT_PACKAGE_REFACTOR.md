# 12 — Engagement Package Refactor Addendum for Events Package

This addendum overrides earlier Events package documentation wherever generic interaction tables were included inside Events.

The system now has a dedicated `aiarmada/engagement` package.

Therefore, the Events package must not create or own generic engagement tables.

## Remove from Events package

Do not create these Events tables:

```text
event_responses
event_subscriptions
event_reminders
follows
bookmarks
bookmark_collections
bookmark_collection_items
reactions
interaction_events
interaction_logs
```

## Move to Engagement package

```text
event_responses      -> engagement.responses
event_subscriptions  -> engagement.subscriptions
event_reminders      -> engagement.reminders
follows              -> engagement.follows
bookmarks            -> engagement.bookmarks
bookmark_collections -> engagement.bookmark_collections
reactions            -> engagement.reactions
```

## Move to Signals (analytics) package

```text
interaction_events
interaction_logs
views
clicks
opens
impressions
```

## Events package still owns

```text
events
event_occurrences
event_sessions
event_registrations
event_registration_participants
event_registration_items
event_passes
event_attendances
event_attendance_logs
event_involvements
event_locations
event_updates
event_change_logs
```

## Add integration contract

Events package must define `EventEngagementManager` and bind a Null implementation by default.

When `aiarmada/engagement` exists, bind the real adapter using `class_exists()`.

## Events package behavior

- Event pages may show follow/bookmark/going/reminder/subscription state only through `EventEngagementManager`.
- Events package must not query engagement tables directly.
- Events package must emit Laravel events such as `EventOccurrencePublished`, `EventUpdatePublished`, `EventStartingSoon`, and `EventRecordingAvailable`.
- Engagement package may listen and match subscriptions/reminders.
- Laravel Notifications deliver actual messages.

## Developer checklist

- [x] Add `EventEngagementManager` contract. (packages/events/src/Contracts/EventEngagementManager.php)
- [x] Add `NullEventEngagementManager`. (packages/events/src/Integrations/NullEventEngagementManager.php)
- [x] Add optional binding to Engagement adapter using `class_exists()`. (EventsServiceProvider)
- [x] Remove engagement table migrations from Events package.
- [x] Remove engagement models from Events package.
- [ ] Remove direct engagement relation managers from Events package. (Filament not built)
- [ ] Update Filament resources to hide engagement widgets when adapter is null. (Filament not built)
- [ ] Update tests to verify Events boots with and without Engagement package. (not started)

# 05 — Events Package Migration and Integration

This document is an explicit instruction for the Events package developer.

The Events package must be updated so it does not own generic interaction tables. Interaction state belongs to the Interactions package.

## Tables/concepts to move out of Events

Remove from Events package migrations/spec:

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

## Replacement in Interactions package

```text
event_responses
→ responses

field mapping:
- responder_type stays responder_type
- responder_id stays responder_id
- event_id / event_occurrence_id / event_session_id becomes respondable_type/respondable_id
- response becomes response_type
- responded_at stays responded_at
- cancelled_at stays cancelled_at
```

```text
event_subscriptions
→ subscriptions

field mapping:
- subscriber_type stays subscriber_type
- subscriber_id stays subscriber_id
- event_id / event_occurrence_id / event_session_id becomes subscribable_type/subscribable_id when concrete
- rule/filter subscriptions use subscribable_type = null and criteria jsonb
- notification_preferences stays notification_preferences
```

```text
event_reminders
→ reminders

field mapping:
- remindable_type/remindable_id replace event_id/occurrence/session columns
- recipient_type/recipient_id stay generic
- reminder_type stays reminder_type
- remind_at/offset_minutes/anchor fields stay in reminders
- delivery is handled by Laravel Notifications
```

```text
follows/bookmarks/reactions
→ same generic table names in Interactions package
```

```text
interaction_events / interaction_logs
→ Analytics package, not Interactions and not Events
```

## Events package should keep

Events still owns operational truth:

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
event_materials
event_references
event_links
event_media
```

## Integration contract in Events package

Events package must define a contract:

```php
namespace AiArmada\Events\Contracts;

interface EventEngagementManager
{
    public function isFollowing(mixed $actor, mixed $target): bool;

    public function follow(mixed $actor, mixed $target, array $options = []): void;

    public function unfollow(mixed $actor, mixed $target, array $options = []): void;

    public function isBookmarked(mixed $actor, mixed $target): bool;

    public function bookmark(mixed $actor, mixed $target, array $options = []): void;

    public function removeBookmark(mixed $actor, mixed $target, array $options = []): void;

    public function responseFor(mixed $actor, mixed $target): ?string;

    public function respond(mixed $actor, mixed $target, string $responseType, array $options = []): void;

    public function subscribe(mixed $actor, mixed $target = null, string $subscriptionType = 'updates', array $criteria = [], array $options = []): void;

    public function setReminder(mixed $actor, mixed $target, string $reminderType, array $options = []): void;
}
```

## Null implementation in Events package

Events must work without Interactions installed.

```php
class NullEventEngagementManager implements EventEngagementManager
{
    public function isFollowing(mixed $actor, mixed $target): bool { return false; }
    public function follow(mixed $actor, mixed $target, array $options = []): void {}
    public function unfollow(mixed $actor, mixed $target, array $options = []): void {}

    public function isBookmarked(mixed $actor, mixed $target): bool { return false; }
    public function bookmark(mixed $actor, mixed $target, array $options = []): void {}
    public function removeBookmark(mixed $actor, mixed $target, array $options = []): void {}

    public function responseFor(mixed $actor, mixed $target): ?string { return null; }
    public function respond(mixed $actor, mixed $target, string $responseType, array $options = []): void {}

    public function subscribe(mixed $actor, mixed $target = null, string $subscriptionType = 'updates', array $criteria = [], array $options = []): void {}
    public function setReminder(mixed $actor, mixed $target, string $reminderType, array $options = []): void {}
}
```

## Real adapter when Interactions is installed

Interactions package provides:

```php
class EngagementEventEngagementManager implements \AiArmada\Events\Contracts\EventEngagementManager
{
    public function isFollowing(mixed $actor, mixed $target): bool
    {
        return app(EngagementStateResolver::class)->isFollowing($actor, $target);
    }

    public function follow(mixed $actor, mixed $target, array $options = []): void
    {
        app(EngagementManager::class)->follow($actor, $target, $options);
    }

    public function respond(mixed $actor, mixed $target, string $responseType, array $options = []): void
    {
        app(EngagementManager::class)->respond($actor, $target, $responseType, $options);
    }

    // implement remaining methods similarly
}
```

## Service provider binding

Events package:

```php
$this->app->bind(EventEngagementManager::class, NullEventEngagementManager::class);

if (class_exists(\AiArmada\Interactions\EngagementServiceProvider::class)
    && class_exists(\AiArmada\Interactions\Integrations\Events\EngagementEventEngagementManager::class)) {
    $this->app->bind(
        EventEngagementManager::class,
        \AiArmada\Interactions\Integrations\Events\EngagementEventEngagementManager::class,
    );
}
```

## Events package model interfaces

Events models may implement Interactions contracts when the package exists, but must not hard require them.

Safer approach:

- Events package defines optional traits in an integration namespace.
- Interactions package provides traits/contracts.
- Host app applies traits to configured event models if desired.

Example:

```php
class EventOccurrence extends Model
{
    // Events core relationships only
}
```

Host app may extend:

```php
class EventOccurrence extends BaseEventOccurrence implements Followable, Bookmarkable, Respondable, Subscribable, Remindable
{
    use HasFollowers;
    use HasBookmarks;
    use HasResponses;
    use HasSubscriptions;
    use HasReminders;
}
```

## Laravel events emitted by Events package

Events package should emit domain events:

```text
EventPublished
EventOccurrencePublished
EventOccurrenceUpdated
EventOccurrenceCancelled
EventOccurrencePostponed
EventOccurrenceRescheduled
EventUpdatePublished
EventLiveLinkAdded
EventRecordingAvailable
EventStartingSoon
```

Interactions package listens to these to match subscriptions/reminders.

Example:

```text
EventOccurrencePublished
→ Interactions SubscriptionMatcher finds subscriptions where criteria.delivery_mode = online
→ host app sends Laravel Notification
```

## Interactions package should not mutate Events state

Interactions can store `going`, but it must not create event registrations.

Correct:

```text
User clicks Going
→ Interactions.responses row created
```

Incorrect:

```text
User clicks Going
→ event_registrations row automatically created
```

Unless the host app explicitly wires a custom listener to do that.

## Filament Events UI integration

Events Filament pages should call `EventEngagementManager` to show optional user state:

```text
Follow count
Bookmark count
Going/interested/maybe counts
Subscribed status
Reminder status
```

If Interactions package is absent, these widgets/actions must hide or show zero gracefully.

## Migration checklist for Events package

- [x] Remove `event_responses` migration from Events. (done)
- [x] Remove `event_subscriptions` migration from Events. (done)
- [x] Remove `event_reminders` migration from Events. (done)
- [x] Remove generic follows/bookmarks/reactions from Events docs/migrations. (done)
- [x] Remove interaction analytics logs from Events docs/migrations. (done)
- [x] Add `EventEngagementManager` contract. (packages/events/src/Contracts/)
- [x] Add `NullEventEngagementManager`. (packages/events/src/Integrations/)
- [x] Add optional Engagement adapter binding using `class_exists()`. (EventsServiceProvider)
- [ ] Update Filament event pages to use the contract instead of direct tables. (Filament not built)
- [ ] Update Events README to reference Engagement package. (not done)
- [ ] Add upgrade notes explaining where moved concepts now live. (not done)

# Rename Interactions Package to Engagement Package + Add Sharing

## Purpose

This document supersedes the previous package naming of **Interactions**.

The package must now be treated as:

```text
aiarmada/engagement
```

Namespace:

```php
AiArmada\Engagement
```

The previous name **Interactions** was technically accurate, but **Engagement** is more suitable because the package owns intentional user engagement state across any model:

```text
follow
bookmark / save
respond / RSVP / intent
react
subscribe
remind
share
```

The package must not become a social network package. It does not own posts, feeds, comments, DMs, public timelines, or analytics logs.

## Boundary

### Engagement package owns

```text
Persistent user intent and preference toward any model.
```

Examples:

```text
User follows a speaker.
User bookmarks an event occurrence.
User responds "going" to an occurrence.
User reacts "useful" to a recording.
User subscribes to all live online events.
User asks to be reminded before an event.
User shares an event to WhatsApp.
```

### Events package owns

```text
Event truth and operational state.
```

Examples:

```text
events
event_occurrences
event_sessions
event_registrations
event_passes
event_attendances
event_updates
event_change_logs
```

### packages/signals owns

```text
Observed/passive tracking and metrics.
```

Examples:

```text
views
clicks
opens
impressions
share link opens
conversion funnels
search logs
map opens
recording opens
```

### Laravel Notifications owns delivery

There is no separate Notifications package for now. The Engagement package may store reminder/subscription preference state, but actual notification delivery should use Laravel Notifications, queued jobs, and application listeners.

Engagement must not own notification delivery logs unless a future notification package is created.

---

# Final naming decision

Use:

```text
Engagement
```

Do not use:

```text
Interactions
Socials
```

Reason:

```text
Interactions = technically acceptable but less product-friendly.
Socials = misleading because it suggests social network features.
Engagement = accurately describes intentional user action and preference.
```

---

# Rename map

## Package

```text
OLD: aiarmada/engagement
NEW: aiarmada/engagement
```

## Namespace

```text
OLD: AiArmada\Interactions
NEW: AiArmada\Engagement
```

## Service provider

```text
OLD: AiArmada\Interactions\EngagementServiceProvider
NEW: AiArmada\Engagement\EngagementServiceProvider
```

## Config

```text
OLD: config/engagement.php
NEW: config/engagement.php
```

## Main service contract

```text
OLD: EngagementManager
NEW: EngagementManager
```

## State resolver

```text
OLD: EngagementStateResolver
NEW: EngagementStateResolver
```

## Counter service

```text
OLD: EngagementCounter
NEW: EngagementCounter
```

## Event integration adapter

```text
OLD: EngagementEventEngagementManager
NEW: EngagementEventEngagementManager
```

## Events package contract name

The Events package should no longer expose `EventEngagementManager` unless already implemented.

Preferred final name:

```php
AiArmada\Events\Contracts\EventEngagementManager
```

Default implementation:

```php
AiArmada\Events\Support\NullEventEngagementManager
```

Bridge implementation when Engagement is installed:

```php
AiArmada\Events\Integrations\Engagement\EngagementEventEngagementManager
```

---

# Table naming decision

Because this is a reusable package, table names should be package-safe and not collide with app/domain tables.

Use prefixed table names by default:

```text
engagement_follows
engagement_bookmarks
engagement_bookmark_collections
engagement_bookmark_collection_items
engagement_responses
engagement_reactions
engagement_subscriptions
engagement_reminders
engagement_shares
```

The table names must be configurable in `config/engagement.php`.

Example:

```php
return [
    'tables' => [
        'follows' => 'engagement_follows',
        'bookmarks' => 'engagement_bookmarks',
        'bookmark_collections' => 'engagement_bookmark_collections',
        'bookmark_collection_items' => 'engagement_bookmark_collection_items',
        'responses' => 'engagement_responses',
        'reactions' => 'engagement_reactions',
        'subscriptions' => 'engagement_subscriptions',
        'reminders' => 'engagement_reminders',
        'shares' => 'engagement_shares',
    ],
];
```

No table in this package should be called `interaction_events` or `interaction_logs`. packages/signals owns that concern.

---

# Global database rules

These rules must match the Events package architecture.

## Primary keys

Every table must use:

```php
$table->uuid('id')->primary();
```

## Timestamps

Use:

```php
$table->timestampsTz();
```

Use specific `_at` columns instead of boolean flags where lifecycle matters.

Examples:

```text
followed_at
muted_at
unfollowed_at
bookmarked_at
removed_at
responded_at
cancelled_at
reacted_at
shared_at
revoked_at
expired_at
subscribed_at
unsubscribed_at
remind_at
sent_at
failed_at
```

## No soft deletes

Do not use:

```php
$table->softDeletes();
$table->softDeletesTz();
```

Use status and lifecycle timestamps instead.

## No database foreign keys

Do not use database-level foreign keys.

Do not cascade deletes.

Relationships are enforced at the application layer.

Store UUID columns plainly:

```php
$table->uuid('bookmark_id');
$table->uuid('bookmark_collection_id');
```

No:

```php
$table->foreign(...)
```

## Polymorphic references

Use explicit morph columns:

```php
$table->string('actor_type');
$table->uuid('actor_id');
```

Do not rely on database constraints for polymorphic relations.

---

# Final Engagement package tables

## Required core tables

```text
engagement_follows
engagement_bookmarks
engagement_bookmark_collections
engagement_bookmark_collection_items
engagement_responses
engagement_reactions
engagement_subscriptions
engagement_reminders
engagement_shares
```

## Tables moved out of Events package

The Events package must no longer own or create these tables:

```text
event_responses
event_subscriptions
event_reminders
follows
bookmarks
bookmark_collections
reactions
interaction_events
interaction_logs
```

Migration target:

```text
event_responses       → engagement_responses
event_subscriptions   → engagement_subscriptions
event_reminders       → engagement_reminders
follows               → engagement_follows
bookmarks             → engagement_bookmarks
bookmark_collections  → engagement_bookmark_collections
reactions             → engagement_reactions
```

packages/signals target:

```text
interaction_events / interaction_logs → packages/signals only
```

---

# Table structures

## 1. `engagement_follows`

Stores entity-based following.

Use for:

```text
follow speaker
follow masjid
follow event
follow event occurrence
follow topic
follow series
follow organizer
```

Columns:

```text
id uuid primary
follower_type string
follower_id uuid
followable_type string
followable_id uuid
status string
notification_level string nullable
notification_preferences jsonb nullable
followed_at timestampTz
muted_at timestampTz nullable
unfollowed_at timestampTz nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Suggested statuses:

```text
active
muted
unfollowed
blocked
```

Suggested notification levels:

```text
none
important_only
all
```

Indexes:

```text
(follower_type, follower_id)
(followable_type, followable_id)
(follower_type, follower_id, followable_type, followable_id)
status
followed_at
```

---

## 2. `engagement_bookmarks`

Stores saved/bookmarked items.

Use for:

```text
save event
save occurrence
save session
save speaker
save masjid
save book
save topic
```

Columns:

```text
id uuid primary
bookmarker_type string
bookmarker_id uuid
bookmarkable_type string
bookmarkable_id uuid
status string
notes text nullable
bookmarked_at timestampTz
removed_at timestampTz nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Suggested statuses:

```text
active
removed
archived
```

Indexes:

```text
(bookmarker_type, bookmarker_id)
(bookmarkable_type, bookmarkable_id)
(bookmarker_type, bookmarker_id, bookmarkable_type, bookmarkable_id)
status
bookmarked_at
```

---

## 3. `engagement_bookmark_collections`

Stores user-owned save folders/lists.

Columns:

```text
id uuid primary
owner_type string
owner_id uuid
name string
description text nullable
visibility string
status string
sort_order integer default 0
archived_at timestampTz nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Suggested visibility:

```text
private
unlisted
public
```

Suggested status:

```text
active
archived
```

Indexes:

```text
(owner_type, owner_id)
visibility
status
sort_order
```

---

## 4. `engagement_bookmark_collection_items`

Links bookmarks to collections.

This is separate from `engagement_bookmarks` because one bookmark can appear in multiple collections.

Columns:

```text
id uuid primary
bookmark_collection_id uuid
bookmark_id uuid
sort_order integer default 0
notes text nullable
added_at timestampTz
removed_at timestampTz nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Indexes:

```text
bookmark_collection_id
bookmark_id
(bookmark_collection_id, bookmark_id)
added_at
removed_at
```

No foreign keys.

---

## 5. `engagement_responses`

Stores user response / RSVP / intent toward any model.

Use for:

```text
interested
going
maybe
not going
attending online
attending physical
want to watch recording
```

Columns:

```text
id uuid primary
responder_type string
responder_id uuid
respondable_type string
respondable_id uuid
response_type string
status string
visibility string
responded_at timestampTz
changed_at timestampTz nullable
cancelled_at timestampTz nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Suggested response types:

```text
interested
going
maybe
not_going
attending_online
attending_physical
want_to_watch_recording
```

Suggested statuses:

```text
active
cancelled
superseded
```

Important distinction:

```text
engagement_responses = intent
Events registration = official signup
Events attendance = actual check-in
```

Indexes:

```text
(responder_type, responder_id)
(respondable_type, respondable_id)
(responder_type, responder_id, respondable_type, respondable_id)
response_type
status
responded_at
```

---

## 6. `engagement_reactions`

Stores lightweight reactions.

Use for:

```text
like
love
useful
support
amin
insightful
funny
```

Columns:

```text
id uuid primary
reactor_type string
reactor_id uuid
reactable_type string
reactable_id uuid
reaction_type string
status string
reacted_at timestampTz
removed_at timestampTz nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Suggested statuses:

```text
active
removed
```

Indexes:

```text
(reactor_type, reactor_id)
(reactable_type, reactable_id)
(reactor_type, reactor_id, reactable_type, reactable_id)
reaction_type
status
reacted_at
```

---

## 7. `engagement_subscriptions`

Stores explicit subscription preferences.

Use this for rule/filter-based subscriptions or advanced update preferences.

Examples:

```text
Subscribe to all live online events.
Subscribe to new occurrences by a speaker.
Subscribe to all Fiqh events near me.
Subscribe to recording availability for a specific occurrence.
```

Columns:

```text
id uuid primary
subscriber_type string
subscriber_id uuid
subscribable_type string nullable
subscribable_id uuid nullable
subscription_type string
status string
criteria jsonb nullable
notification_level string nullable
notification_preferences jsonb nullable
subscribed_at timestampTz
muted_at timestampTz nullable
unsubscribed_at timestampTz nullable
expires_at timestampTz nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Suggested subscription types:

```text
updates
important_changes
new_occurrences
recording_available
starting_soon
discovery_alert
digest
```

Suggested statuses:

```text
active
muted
unsubscribed
expired
```

Indexes:

```text
(subscriber_type, subscriber_id)
(subscribable_type, subscribable_id)
subscription_type
status
subscribed_at
expires_at
```

Example criteria for live online event subscription:

```json
{
  "subject": "event_occurrence",
  "delivery_mode": "online",
  "live": true,
  "status": "published"
}
```

---

## 8. `engagement_reminders`

Stores user reminder preferences/scheduled reminder state.

Actual notification delivery should use Laravel Notifications.

Columns:

```text
id uuid primary
reminder_owner_type string
reminder_owner_id uuid
remindable_type string
remindable_id uuid
reminder_type string
status string
remind_at timestampTz nullable
offset_minutes integer nullable
anchor_type string nullable
anchor_value string nullable
channel string nullable
sent_at timestampTz nullable
cancelled_at timestampTz nullable
failed_at timestampTz nullable
failure_reason text nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Suggested reminder types:

```text
before_start
when_live_starts
when_recording_available
custom
```

Suggested statuses:

```text
scheduled
sent
cancelled
failed
expired
```

Important:

```text
engagement_reminders = user reminder state
Laravel Notifications = delivery
packages/signals = opens/clicks
```

Indexes:

```text
(reminder_owner_type, reminder_owner_id)
(remindable_type, remindable_id)
reminder_type
status
remind_at
sent_at
cancelled_at
```

---

## 9. `engagement_shares`

Stores intentional share actions.

Use for:

```text
share event to WhatsApp
share occurrence to Telegram
copy event link
generate QR share link
share speaker profile
share masjid page
share book page
```

Columns:

```text
id uuid primary
sharer_type string nullable
sharer_id uuid nullable
shareable_type string
shareable_id uuid
channel string nullable
destination string nullable
share_url text nullable
share_token string nullable
message text nullable
status string
share_intent_at timestampTz nullable
shared_at timestampTz nullable
revoked_at timestampTz nullable
expired_at timestampTz nullable
failed_at timestampTz nullable
failure_reason text nullable
metadata jsonb nullable
created_at timestampTz
updated_at timestampTz
```

Suggested channels:

```text
whatsapp
telegram
facebook
x
linkedin
email
copy_link
qr
native_share
other
```

Suggested statuses:

```text
created
shared
revoked
expired
failed
```

Important boundary:

```text
engagement_shares = user intentionally shared/generated a share
packages/signals = whether the shared link was opened/clicked/converted
```

Indexes:

```text
(sharer_type, sharer_id)
(shareable_type, shareable_id)
channel
status
share_token
shared_at
expired_at
```

---

# Contracts

## `Followable`

```php
interface Followable
{
    public function followableName(): string;
    public function followableUrl(): ?string;
    public function followableImage(): ?string;
}
```

## `Bookmarkable`

```php
interface Bookmarkable
{
    public function bookmarkTitle(): string;
    public function bookmarkUrl(): ?string;
    public function bookmarkImage(): ?string;
}
```

## `Respondable`

```php
interface Respondable
{
    public function allowedResponseTypes(): array;
}
```

## `Reactable`

```php
interface Reactable
{
    public function allowedReactionTypes(): array;
}
```

## `Subscribable`

```php
interface Subscribable
{
    public function subscribableName(): string;
    public function availableSubscriptionTypes(): array;
}
```

## `Remindable`

```php
interface Remindable
{
    public function reminderTitle(): string;
    public function reminderTargetTime(): ?\DateTimeInterface;
}
```

## `Shareable`

```php
interface Shareable
{
    public function shareTitle(): string;
    public function shareUrl(): string;
    public function shareDescription(): ?string;
    public function shareImage(): ?string;
}
```

---

# Traits

Use these reusable traits where appropriate.

## Subject-side traits

```php
HasFollowers
HasBookmarks
HasResponses
HasReactions
HasSubscriptions
HasReminders
HasShares
```

## Actor-side traits

```php
CanFollow
CanBookmark
CanRespond
CanReact
CanSubscribe
CanSetReminders
CanShare
```

Example usage:

```php
class EventOccurrence extends Model implements Followable, Bookmarkable, Respondable, Subscribable, Remindable, Shareable
{
    use HasFollowers;
    use HasBookmarks;
    use HasResponses;
    use HasSubscriptions;
    use HasReminders;
    use HasShares;
}
```

```php
class User extends Model
{
    use CanFollow;
    use CanBookmark;
    use CanRespond;
    use CanReact;
    use CanSubscribe;
    use CanSetReminders;
    use CanShare;
}
```

---

# Services

## `EngagementManager`

```php
interface EngagementManager
{
    public function follow(mixed $actor, mixed $subject, array $options = []): mixed;
    public function unfollow(mixed $actor, mixed $subject): void;

    public function bookmark(mixed $actor, mixed $subject, array $options = []): mixed;
    public function removeBookmark(mixed $actor, mixed $subject): void;

    public function respond(mixed $actor, mixed $subject, string $responseType, array $options = []): mixed;
    public function cancelResponse(mixed $actor, mixed $subject): void;

    public function react(mixed $actor, mixed $subject, string $reactionType, array $options = []): mixed;
    public function removeReaction(mixed $actor, mixed $subject, ?string $reactionType = null): void;

    public function subscribe(mixed $actor, mixed $subject = null, array $options = []): mixed;
    public function unsubscribe(mixed $actor, mixed $subject = null, array $options = []): void;

    public function remind(mixed $actor, mixed $subject, array $options = []): mixed;
    public function cancelReminder(mixed $actor, mixed $subject, array $options = []): void;

    public function share(mixed $actor, mixed $subject, array $options = []): mixed;
}
```

## `EngagementStateResolver`

```php
interface EngagementStateResolver
{
    public function isFollowing(mixed $actor, mixed $subject): bool;
    public function isBookmarked(mixed $actor, mixed $subject): bool;
    public function responseFor(mixed $actor, mixed $subject): ?string;
    public function reactionFor(mixed $actor, mixed $subject): ?string;
    public function isSubscribed(mixed $actor, mixed $subject = null, array $criteria = []): bool;
}
```

## `EngagementCounter`

```php
interface EngagementCounter
{
    public function followersCount(mixed $subject): int;
    public function bookmarksCount(mixed $subject): int;
    public function responsesCount(mixed $subject, ?string $responseType = null): int;
    public function reactionsCount(mixed $subject, ?string $reactionType = null): int;
    public function sharesCount(mixed $subject, ?string $channel = null): int;
}
```

## `ShareUrlGenerator`

```php
interface ShareUrlGenerator
{
    public function generateShareUrl(mixed $shareable, array $options = []): string;
}
```

The share URL generator may append tracking parameters, but packages/signals owns tracking records.

---

# Laravel events emitted by Engagement package

These are application events, not database tables.

```text
FollowCreated
FollowMuted
FollowRemoved

BookmarkCreated
BookmarkRemoved
BookmarkAddedToCollection
BookmarkRemovedFromCollection

ResponseCreated
ResponseChanged
ResponseCancelled

ReactionCreated
ReactionRemoved

SubscriptionCreated
SubscriptionMuted
SubscriptionCancelled
SubscriptionExpired

ReminderScheduled
ReminderCancelled
ReminderDue
ReminderSent
ReminderFailed

ShareCreated
ShareCompleted
ShareRevoked
ShareExpired
ShareFailed
```

packages/signals may listen to these.

Events package may listen to `ResponseCreated` if it wants to update UI/counters.

Laravel Notifications may listen to `ReminderDue`, `SubscriptionCreated`, or domain events such as `EventOccurrencePublished`.

---

# Events package integration

The Events package must integrate with Engagement through contracts, not direct table access.

## Events package contract

```php
namespace AiArmada\Events\Contracts;

interface EventEngagementManager
{
    public function follow(mixed $actor, mixed $eventTarget, array $options = []): mixed;
    public function bookmark(mixed $actor, mixed $eventTarget, array $options = []): mixed;
    public function respond(mixed $actor, mixed $eventTarget, string $responseType, array $options = []): mixed;
    public function subscribe(mixed $actor, mixed $eventTarget = null, array $options = []): mixed;
    public function remind(mixed $actor, mixed $eventTarget, array $options = []): mixed;
    public function share(mixed $actor, mixed $eventTarget, array $options = []): mixed;
    public function stateFor(mixed $actor, mixed $eventTarget): array;
}
```

## Null implementation

```php
class NullEventEngagementManager implements EventEngagementManager
{
    public function follow(mixed $actor, mixed $eventTarget, array $options = []): mixed { return null; }
    public function bookmark(mixed $actor, mixed $eventTarget, array $options = []): mixed { return null; }
    public function respond(mixed $actor, mixed $eventTarget, string $responseType, array $options = []): mixed { return null; }
    public function subscribe(mixed $actor, mixed $eventTarget = null, array $options = []): mixed { return null; }
    public function remind(mixed $actor, mixed $eventTarget, array $options = []): mixed { return null; }
    public function share(mixed $actor, mixed $eventTarget, array $options = []): mixed { return null; }
    public function stateFor(mixed $actor, mixed $eventTarget): array { return []; }
}
```

## Engagement bridge binding

In the Events package service provider:

```php
if (class_exists(\AiArmada\Engagement\EngagementServiceProvider::class)) {
    $this->app->bind(
        \AiArmada\Events\Contracts\EventEngagementManager::class,
        \AiArmada\Events\Integrations\Engagement\EngagementEventEngagementManager::class,
    );
}
```

If Engagement is not installed, Events must still work.

---

# Migration/refactor instructions for Events package

## Remove from Events package migrations

The Events package must not create:

```text
event_responses
event_subscriptions
event_reminders
follows
bookmarks
bookmark_collections
reactions
interaction_events
interaction_logs
```

## Update Events package documents

Where older docs mention `Interactions`, update to `Engagement`.

Where older docs mention:

```text
EventEngagementManager
```

rename to:

```text
EventEngagementManager
```

Where older docs mention:

```text
interactions_package_handoff
```

rename future docs/folders to:

```text
engagement_package_handoff
```

## Data migration from old Events tables

If old Events package tables exist, migrate data into Engagement tables before dropping/retiring old tables.

### `event_responses` → `engagement_responses`

Map:

```text
event_responses.responder_type       → engagement_responses.responder_type
event_responses.responder_id         → engagement_responses.responder_id
event_id / occurrence_id / session_id → respondable_type/respondable_id
event_responses.response             → engagement_responses.response_type
event_responses.status               → engagement_responses.status
event_responses.responded_at         → engagement_responses.responded_at
```

Target selection rule:

```text
If event_session_id exists:
  respondable_type = EventSession
  respondable_id = event_session_id
Else if event_occurrence_id exists:
  respondable_type = EventOccurrence
  respondable_id = event_occurrence_id
Else:
  respondable_type = Event
  respondable_id = event_id
```

### `event_subscriptions` → `engagement_subscriptions`

Map:

```text
subscriber_type / subscriber_id preserved
subscribable_type/subscribable_id resolved from event/session/occurrence
subscription_type preserved or mapped
notification preferences preserved
subscribed_at preserved
muted_at preserved
unsubscribed_at preserved
```

### `event_reminders` → `engagement_reminders`

Map:

```text
remindable_type/remindable_id resolved from event/session/occurrence
recipient/reminder owner preserved
reminder_type preserved
remind_at preserved
offset_minutes preserved
status preserved
```

## Do not drop data immediately

Because this package avoids irreversible mistakes, migration should:

```text
1. Backup data.
2. Create Engagement tables.
3. Copy data into Engagement tables.
4. Validate row counts and sample records.
5. Update application reads to Engagement.
6. Stop writing to old Events interaction tables.
7. Keep old tables temporarily.
8. Drop old tables only in a later explicit cleanup migration if approved.
```

---

# Sharing behavior

## Share records are not analytics

When a user shares something, Engagement may create:

```text
engagement_shares
```

But when another user opens the shared link, packages/signals must record:

```text
share link opened
conversion from shared link to registration
```

## Share token

`share_token` may be used for generated share URLs.

Example:

```text
https://example.com/e/abc?share=SHARE_TOKEN
```

packages/signals may use this token to attribute traffic, but the ownership of click/open records remains packages/signals.

## Anonymous sharing

`sharer_type` and `sharer_id` are nullable because a guest may copy/share a public link.

---

# Filament admin additions

The Engagement package should provide optional Filament resources/pages for administration.

## Resources

```text
FollowResource
BookmarkResource
BookmarkCollectionResource
ResponseResource
ReactionResource
SubscriptionResource
ReminderResource
ShareResource
```

## Widgets

```text
EngagementOverviewWidget
TopFollowedSubjectsWidget
TopBookmarkedSubjectsWidget
ResponseBreakdownWidget
TopSharedSubjectsWidget
```

## Events package Filament integration

Events resources should show Engagement state through relation managers or widgets only if Engagement is installed.

Example on EventOccurrenceResource:

```text
Responses tab
Bookmarks tab
Followers tab
Shares tab
Reminders tab
```

Guard with:

```php
class_exists(\AiArmada\Engagement\Models\Response::class)
```

or better:

```php
app()->bound(\AiArmada\Events\Contracts\EventEngagementManager::class)
```

---

# Parallel implementation checklist

## Agent A — Rename package identity

Goal: Rename Interactions to Engagement without changing behavior.

Tasks:

```text
[x] Rename composer package to aiarmada/engagement.
[x] Rename namespace to AiArmada\Engagement.
[x] Rename service provider to EngagementServiceProvider.
[x] Rename config file to engagement.php.
[x] Rename InteractionManager to EngagementManager.
[x] Rename InteractionStateResolver to EngagementStateResolver.
[x] Rename InteractionCounter to EngagementCounter.
[~] Update docs and README references. (partially done; some doc references still use old package name in context)
[x] Ensure old naming does not remain in public API unless a compatibility alias is intentionally provided. (verified: no old class names in src/)
```

## Agent B — Table and migration update

Goal: Create final Engagement package database schema.

Tasks:

```text
[x] Create engagement_follows table. (migration + config exist, prefixed by default)
[x] Create engagement_bookmarks table.
[x] Create engagement_bookmark_collections table.
[x] Create engagement_bookmark_collection_items table.
[x] Create engagement_responses table.
[x] Create engagement_reactions table.
[x] Create engagement_subscriptions table.
[x] Create engagement_reminders table.
[x] Create engagement_shares table.
[x] Use uuid primary keys.
[x] Use timestampTz/timestampsTz.
[x] Do not use soft deletes.
[x] Do not use database foreign keys.
[x] Do not use cascading.
[x] Add practical indexes.
[x] Make table names configurable.
```

## Agent C — Sharing feature

Goal: Implement share support.

Tasks:

```text
[x] Create Share model.
[x] Create Shareable contract.
[x] Create HasShares trait.
[x] Create CanShare trait.
[x] Create ShareUrlGenerator contract.
[x] Create default share URL generator.
[x] Create EngagementManager::share().
[x] Emit ShareCreated / ShareCompleted / ShareFailed events. (also ShareRevoked, ShareExpired)
[x] Ensure packages/signals can listen but Engagement does not write analytics logs.
```

## Agent D — Events package refactor

Goal: Events integrates Engagement and stops owning engagement state.

Tasks:

```text
[ ] Remove event_responses migration from Events. (still present in events package)
[ ] Remove event_subscriptions migration from Events. (still present)
[ ] Remove event_reminders migration from Events. (still present)
[ ] Remove follows/bookmarks/reactions/interaction logs from Events docs/migrations. (still present)
[x] Add EventEngagementManager contract.
[x] Add NullEventEngagementManager.
[x] Add EngagementEventEngagementManager bridge.
[x] Bind bridge only when Engagement package exists. (EventsServiceProvider class_exists check)
[ ] Update Events Filament resources to show Engagement panels only when available. (Filament not built)
[ ] Update Events docs to reference Engagement package. (not done)
```

## Agent E — Laravel Notifications integration

Goal: Use Laravel Notifications for delivery without creating a notification package.

Tasks:

```text
[x] Create notification classes for reminders/subscriptions if needed. (EngagementReminderNotification)
[x] Ensure engagement_reminders stores reminder state only. (verified: no delivery logs)
[~] Dispatch queued notifications when reminders are due. (ReminderDue event dispatched; queueing left to host app)
[x] Do not store delivery logs in Engagement unless application explicitly needs it. (verified by lint)
[~] Allow app to override notification classes. (channels configurable; class override not wired)
```

## Agent F — Tests

Goal: Verify rename, schema, behavior, and integration.

Tasks:

```text
[ ] Test follow/unfollow. (not started)
[ ] Test bookmark/remove bookmark. (not started)
[ ] Test collections and collection items. (not started)
[ ] Test response create/change/cancel. (not started)
[ ] Test reactions. (not started)
[ ] Test subscriptions with criteria. (not started)
[ ] Test reminders scheduling state. (not started)
[ ] Test shares with anonymous and authenticated sharer. (not started)
[ ] Test Events works without Engagement installed. (not started)
[ ] Test Events bridge works when Engagement is installed. (not started)
[ ] Test no DB foreign keys exist. (can verify by lint, no test written)
[ ] Test no soft delete columns exist. (can verify by lint, no test written)
```

---

# Final rule

Use:

```text
Engagement = intentional user state and preference.
Events = event truth and operations.
packages/signals = passive tracking and metrics.
Laravel Notifications = delivery.
Commerce = paid access, products, orders, invoices.
Authorization = permissions.
```

This rename makes the package easier to understand and safer to extend. It can support sharing without pretending to be a full social media platform.

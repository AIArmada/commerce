# 02 — Database Schema

## Global migration rules

Every table must use:

```php
$table->uuid('id')->primary();
```

Every normal mutable table must use:

```php
$table->timestampsTz();
```

Use `timestampTz()` for lifecycle columns.

Do not create:

```php
$table->softDeletes();
$table->foreign(...);
$table->cascadeOnDelete();
```

No soft deletes, no database foreign keys, no cascading.

Use UUID reference columns and indexes only.

Example:

```php
$table->uuid('followable_id')->index();
$table->string('followable_type')->index();
```

## Table list

Core tables:

```text
follows
bookmarks
bookmark_collections
bookmark_collection_items
responses
reactions
subscriptions
reminders
```

Optional counter table:

```text
interaction_counters
```

The counter table is optional and should be used only if live aggregate counts become expensive.

---

# 1. `follows`

Stores entity-based following.

Use for:

```text
follow event
follow event occurrence
follow speaker
follow masjid
follow organization
follow topic
follow series
follow book
```

## Columns

```text
follows
- id uuid primary

- follower_type string indexed
- follower_id uuid indexed

- followable_type string indexed
- followable_id uuid indexed

- status string indexed
- notification_level string nullable indexed
- notification_preferences jsonb nullable

- followed_at timestampTz nullable indexed
- muted_at timestampTz nullable
- unfollowed_at timestampTz nullable indexed
- blocked_at timestampTz nullable

- source string nullable indexed
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Status codes

```text
active
muted
unfollowed
blocked
```

## Notification level codes

```text
none
important_only
all
```

## Unique/index guidance

Application must prevent multiple active follow rows for the same follower/followable pair.

Recommended index:

```text
[follower_type, follower_id, followable_type, followable_id, status]
```

Do not use FK constraints.

---

# 2. `bookmarks`

Stores saved items.

## Columns

```text
bookmarks
- id uuid primary

- bookmarker_type string indexed
- bookmarker_id uuid indexed

- bookmarkable_type string indexed
- bookmarkable_id uuid indexed

- status string indexed
- notes text nullable

- bookmarked_at timestampTz nullable indexed
- removed_at timestampTz nullable indexed
- archived_at timestampTz nullable

- source string nullable indexed
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Status codes

```text
active
removed
archived
```

## Important behavior

Removing a bookmark should set:

```text
status = removed
removed_at = now()
```

Do not delete the row unless the host app explicitly performs hard cleanup.

---

# 3. `bookmark_collections`

Stores user-owned bookmark lists/folders.

## Columns

```text
bookmark_collections
- id uuid primary

- owner_type string indexed
- owner_id uuid indexed

- name string
- slug string nullable indexed
- description text nullable

- visibility string indexed
- status string indexed
- sort_order integer default 0

- is_default boolean default false
- is_system boolean default false

- archived_at timestampTz nullable
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Visibility codes

```text
private
unlisted
public
```

## Status codes

```text
active
archived
locked
```

---

# 4. `bookmark_collection_items`

Connects bookmarks to collections.

This is separate from `bookmarks.collection_id` because one bookmark may belong to many collections.

## Columns

```text
bookmark_collection_items
- id uuid primary

- bookmark_collection_id uuid indexed
- bookmark_id uuid indexed

- sort_order integer default 0
- notes text nullable

- added_at timestampTz nullable indexed
- removed_at timestampTz nullable indexed

- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Behavior

Removing from a collection sets:

```text
removed_at = now()
```

The bookmark itself remains active unless removed separately.

---

# 5. `responses`

Stores explicit user response/intent toward any model.

This replaces event-specific `event_responses`.

## Columns

```text
responses
- id uuid primary

- responder_type string indexed
- responder_id uuid indexed

- respondable_type string indexed
- respondable_id uuid indexed

- response_type string indexed
- status string indexed
- visibility string indexed

- responded_at timestampTz nullable indexed
- changed_at timestampTz nullable
- cancelled_at timestampTz nullable indexed
- expires_at timestampTz nullable

- source string nullable indexed
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Common response types

```text
interested
going
maybe
not_going
attending_online
attending_physical
want_recording
```

Host packages may define additional response types through `Respondable::allowedResponseTypes()`.

## Status codes

```text
active
changed
cancelled
expired
```

## Visibility codes

```text
private
public
followers_only
managers_only
```

## Important behavior

Changing response should not blindly overwrite without state awareness.

When user changes from `interested` to `going`:

```text
response_type = going
status = active
changed_at = now()
metadata.previous_response_type = interested
```

Analytics may listen to `ResponseChanged`, but Analytics must not own current response state.

---

# 6. `reactions`

Stores lightweight reactions toward any model.

## Columns

```text
reactions
- id uuid primary

- reactor_type string indexed
- reactor_id uuid indexed

- reactable_type string indexed
- reactable_id uuid indexed

- reaction_type string indexed
- status string indexed

- reacted_at timestampTz nullable indexed
- removed_at timestampTz nullable indexed

- source string nullable indexed
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Common reaction types

```text
like
love
useful
support
amin
insightful
funny
```

## Status codes

```text
active
removed
```

---

# 7. `subscriptions`

Stores rule/filter-based interest.

Use this when the user subscribes to a category of future things, not just a specific entity.

Examples:

```text
Notify me about all live online events
Notify me when recordings are available
Notify me about new Fiqh kuliah near me
Notify me about new events by followed speakers
```

## Columns

```text
subscriptions
- id uuid primary

- subscriber_type string indexed
- subscriber_id uuid indexed

- subscribable_type string nullable indexed
- subscribable_id uuid nullable indexed

- subscription_type string indexed
- status string indexed

- criteria jsonb nullable

- notification_level string nullable indexed
- notification_preferences jsonb nullable

- subscribed_at timestampTz nullable indexed
- muted_at timestampTz nullable
- unsubscribed_at timestampTz nullable indexed
- expires_at timestampTz nullable indexed

- source string nullable indexed
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Subscription type examples

```text
updates
important_changes
new_occurrences
recording_available
starting_soon
digest
discovery_alert
live_online_events
nearby_events
```

## Example criteria for live online event subscription

```json
{
  "subject_type": "EventOccurrence",
  "delivery_mode": "online",
  "has_live_link": true,
  "status": "published"
}
```

## Status codes

```text
active
muted
unsubscribed
expired
```

## Follow vs subscription

```text
follows
= user follows a concrete entity

subscriptions
= user subscribes to a rule/filter or notification category
```

---

# 8. `reminders`

Stores user-requested reminder preferences.

Actual sending uses Laravel Notifications. This package only stores the reminder request and dispatches reminder-related events/jobs.

## Columns

```text
reminders
- id uuid primary

- remindable_type string indexed
- remindable_id uuid indexed

- recipient_type string indexed
- recipient_id uuid indexed

- reminder_type string indexed
- status string indexed

- remind_at timestampTz nullable indexed
- offset_minutes integer nullable
- anchor_type string nullable indexed
- anchor_code string nullable indexed

- channel string nullable indexed
- notification_class string nullable

- scheduled_at timestampTz nullable
- sent_at timestampTz nullable indexed
- cancelled_at timestampTz nullable indexed
- failed_at timestampTz nullable
- expires_at timestampTz nullable

- failure_reason text nullable
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Reminder type examples

```text
before_start
when_live_starts
when_recording_available
custom_time
new_occurrence_digest
```

## Status codes

```text
pending
scheduled
sent
cancelled
failed
expired
```

## Important rule

Do not build a separate Notifications package. Use Laravel Notifications and host-app notification channels.

`reminders` store intent and schedule state only.

---

# 9. Optional `interaction_counters`

Use only when on-the-fly aggregate counts are too expensive.

## Columns

```text
interaction_counters
- id uuid primary

- subject_type string indexed
- subject_id uuid indexed

- counter_type string indexed
- counter_key string nullable indexed

- count_value bigint default 0

- recalculated_at timestampTz nullable
- metadata jsonb nullable

- created_at timestampTz
- updated_at timestampTz
```

## Examples

```text
subject = Speaker 123
counter_type = followers
count_value = 2000

subject = EventOccurrence 456
counter_type = responses
counter_key = going
count_value = 150
```

## Important behavior

Counters are derived data.

The source of truth remains:

```text
follows
bookmarks
responses
reactions
subscriptions
```

Provide a reconciliation command:

```bash
php artisan engagement:recalculate-counters
```

---

# Tables explicitly not created here

The Interactions package must not create:

```text
events
event_occurrences
event_sessions
event_registrations
event_attendances
event_passes
analytics_events
interaction_events
interaction_logs
page_views
link_clicks
notification_batches
notification_deliveries
orders
payments
invoices
permissions
roles
```

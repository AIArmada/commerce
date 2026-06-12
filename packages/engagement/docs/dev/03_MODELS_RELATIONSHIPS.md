# 03 — Models and Relationships

## Model list

```text
Follow
Bookmark
BookmarkCollection
BookmarkCollectionItem
Response
Reaction
Subscription
Reminder
EngagementCounter optional
```

## Polymorphic pattern

Every interaction model must support external models through polymorphic columns.

Example:

```text
follower_type / follower_id
followable_type / followable_id
```

Do not use database foreign keys. Define Eloquent `morphTo()` relations only.

## Follow

### Relationships

```php
class Follow extends Model
{
    public function follower(): MorphTo;
    public function followable(): MorphTo;
}
```

### Scopes

```php
scopeActive()
scopeMuted()
scopeUnfollowed()
scopeForFollower($follower)
scopeForFollowable($followable)
scopeNotificationLevel(string $level)
```

### Behavior

- `follow()` creates or reactivates a follow.
- `unfollow()` sets `status = unfollowed` and `unfollowed_at`.
- `mute()` sets `status = muted` and `muted_at`.
- `unmute()` sets `status = active` and clears or preserves muted history according to service policy.

## Bookmark

### Relationships

```php
class Bookmark extends Model
{
    public function bookmarker(): MorphTo;
    public function bookmarkable(): MorphTo;
    public function collectionItems(): HasMany;
}
```

### Scopes

```php
scopeActive()
scopeRemoved()
scopeArchived()
scopeForBookmarker($bookmarker)
scopeForBookmarkable($bookmarkable)
```

### Behavior

- `bookmark()` creates or reactivates a bookmark.
- `removeBookmark()` sets `status = removed` and `removed_at`.
- `archiveBookmark()` sets `status = archived` and `archived_at`.

## BookmarkCollection

### Relationships

```php
class BookmarkCollection extends Model
{
    public function owner(): MorphTo;
    public function items(): HasMany;
}
```

### Behavior

- Collection may be private, unlisted, or public.
- Collection may include bookmarks of different model types.
- Collection is user-owned or organization-owned.

## BookmarkCollectionItem

### Relationships

```php
class BookmarkCollectionItem extends Model
{
    public function collection(): BelongsTo;
    public function bookmark(): BelongsTo;
}
```

No database FK constraints. Use relationship methods for application behavior only.

## Response

### Relationships

```php
class Response extends Model
{
    public function responder(): MorphTo;
    public function respondable(): MorphTo;
}
```

### Scopes

```php
scopeActive()
scopeResponseType(string $type)
scopeForResponder($responder)
scopeForRespondable($respondable)
scopePublic()
```

### Behavior

- A user should usually have only one active response per respondable.
- Changing response updates `response_type`, sets `changed_at`, and emits `ResponseChanged`.
- Cancelling response sets `status = cancelled` and `cancelled_at`.

### Events usage

For event occurrence intent:

```text
respondable_type = EventOccurrence
response_type = going
```

This does not mean registered or attended.

## Reaction

### Relationships

```php
class Reaction extends Model
{
    public function reactor(): MorphTo;
    public function reactable(): MorphTo;
}
```

### Behavior

- A model may allow one reaction per reactor or multiple reaction types depending on `Reactable::reactionPolicy()`.
- Removing reaction sets `status = removed` and `removed_at`.

## Subscription

### Relationships

```php
class Subscription extends Model
{
    public function subscriber(): MorphTo;
    public function subscribable(): MorphTo; // nullable
}
```

### Behavior

Subscriptions support both concrete and rule-based cases.

Concrete subscription:

```text
subscriber = User 123
subscribable = Speaker 456
subscription_type = new_occurrences
```

Rule-based subscription:

```text
subscriber = User 123
subscribable = null
subscription_type = live_online_events
criteria.delivery_mode = online
```

### Follow vs subscription

Use follow when the subject is a concrete entity and the action is socially simple.

Use subscription when notification conditions are more specific or filter-based.

## Reminder

### Relationships

```php
class Reminder extends Model
{
    public function recipient(): MorphTo;
    public function remindable(): MorphTo;
}
```

### Behavior

- Reminder stores a scheduled user preference.
- Laravel Notifications deliver the actual notification.
- Reminder may be anchored by direct `remind_at` or computed using `offset_minutes` and an anchor.

Example:

```text
remindable = EventOccurrence
reminder_type = before_start
offset_minutes = 60
```

## EngagementCounter optional

### Relationships

```php
class EngagementCounter extends Model
{
    public function subject(): MorphTo;
}
```

### Behavior

- Derived aggregate only.
- Must be recalculable.
- Must not be treated as source of truth.

## Traits on external models

Events package models may use:

```php
class EventOccurrence extends Model implements Followable, Bookmarkable, Respondable, Subscribable
{
    use HasFollowers;
    use HasBookmarks;
    use HasResponses;
    use HasSubscriptions;
    use HasReminders;
}
```

ilmu360 models may use:

```php
class Masjid extends Model implements Followable, Bookmarkable, Subscribable
{
    use HasFollowers;
    use HasBookmarks;
    use HasSubscriptions;
}

class Speaker extends Model implements Followable, Bookmarkable, Subscribable
{
    use HasFollowers;
    use HasBookmarks;
    use HasSubscriptions;
}

class Kitab extends Model implements Bookmarkable, Reactable
{
    use HasBookmarks;
    use HasReactions;
}
```

## Actor traits

The user model may use:

```php
class User extends Authenticatable implements CanInteract
{
    use CanFollow;
    use CanBookmark;
    use CanRespond;
    use CanReact;
    use CanSubscribe;
    use CanSetReminders;
}
```

## Important integrity rules

Application services must enforce:

- no duplicate active follows for the same follower/followable;
- no duplicate active bookmark for same bookmarker/bookmarkable;
- one active response per responder/respondable unless explicitly configured;
- reaction multiplicity based on `Reactable` policy;
- subscription uniqueness by subscriber + subscribable/criteria + subscription_type;
- reminder uniqueness where configured by target + reminder_type + recipient.

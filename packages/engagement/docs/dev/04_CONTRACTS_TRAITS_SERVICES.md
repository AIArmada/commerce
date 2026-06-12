# 04 — Contracts, Traits, Services, and Events

## Main contracts

### `Followable`

```php
interface Followable
{
    public function followableName(): string;

    public function followableUrl(): ?string;

    public function followableImage(): ?string;

    public function defaultFollowNotificationLevel(): ?string;
}
```

### `Bookmarkable`

```php
interface Bookmarkable
{
    public function bookmarkTitle(): string;

    public function bookmarkUrl(): ?string;

    public function bookmarkImage(): ?string;
}
```

### `Respondable`

```php
interface Respondable
{
    /** @return array<string> */
    public function allowedResponseTypes(): array;

    public function defaultResponseVisibility(): string;

    public function allowsMultipleResponsesFromSameResponder(): bool;
}
```

### `Reactable`

```php
interface Reactable
{
    /** @return array<string> */
    public function allowedReactionTypes(): array;

    public function allowsMultipleReactionTypesFromSameReactor(): bool;
}
```

### `Subscribable`

```php
interface Subscribable
{
    public function subscribableName(): string;

    /** @return array<string> */
    public function availableSubscriptionTypes(): array;

    public function defaultSubscriptionNotificationLevel(): ?string;
}
```

### `Remindable`

```php
interface Remindable
{
    public function remindableName(): string;

    public function reminderAnchorTime(string $anchorType, ?string $anchorCode = null): ?DateTimeInterface;

    /** @return array<string> */
    public function allowedReminderTypes(): array;
}
```

### `CanInteract`

```php
interface CanInteract
{
    public function interactionDisplayName(): string;

    public function interactionNotificationRoute(?string $channel = null): mixed;
}
```

## Traits for interactable models

```php
trait HasFollowers
trait HasBookmarks
trait HasResponses
trait HasReactions
trait HasSubscriptions
trait HasReminders
```

Example relationship style:

```php
trait HasFollowers
{
    public function follows(): MorphMany
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    public function activeFollows(): MorphMany
    {
        return $this->follows()->where('status', 'active');
    }
}
```

## Traits for actor models

```php
trait CanFollow
trait CanBookmark
trait CanRespond
trait CanReact
trait CanSubscribe
trait CanSetReminders
```

These traits should call services, not implement complex logic directly.

Bad:

```php
$user->follows()->create([...]);
```

Good:

```php
app(EngagementManager::class)->follow($user, $speaker);
```

## Service contracts

### `EngagementManager`

```php
interface EngagementManager
{
    public function follow(mixed $actor, mixed $subject, array $options = []): Follow;

    public function unfollow(mixed $actor, mixed $subject, array $options = []): void;

    public function muteFollow(mixed $actor, mixed $subject, array $options = []): Follow;

    public function bookmark(mixed $actor, mixed $subject, array $options = []): Bookmark;

    public function removeBookmark(mixed $actor, mixed $subject, array $options = []): void;

    public function respond(mixed $actor, mixed $subject, string $responseType, array $options = []): Response;

    public function cancelResponse(mixed $actor, mixed $subject, array $options = []): void;

    public function react(mixed $actor, mixed $subject, string $reactionType, array $options = []): Reaction;

    public function removeReaction(mixed $actor, mixed $subject, ?string $reactionType = null, array $options = []): void;
}
```

### `SubscriptionManager`

```php
interface SubscriptionManager
{
    public function subscribe(mixed $subscriber, mixed $subject = null, string $subscriptionType = 'updates', array $criteria = [], array $options = []): Subscription;

    public function unsubscribe(mixed $subscriber, mixed $subject = null, string $subscriptionType = 'updates', array $criteria = []): void;

    public function muteSubscription(Subscription $subscription): Subscription;

    public function matchingSubscriptions(mixed $subject, string $trigger, array $context = []): iterable;
}
```

### `ReminderManager`

```php
interface ReminderManager
{
    public function setReminder(mixed $recipient, mixed $subject, string $reminderType, array $options = []): Reminder;

    public function cancelReminder(mixed $recipient, mixed $subject, string $reminderType, array $options = []): void;

    public function dueReminders(?DateTimeInterface $at = null): iterable;

    public function markSent(Reminder $reminder): void;

    public function markFailed(Reminder $reminder, string $reason): void;
}
```

### `EngagementStateResolver`

```php
interface EngagementStateResolver
{
    public function isFollowing(mixed $actor, mixed $subject): bool;

    public function isBookmarked(mixed $actor, mixed $subject): bool;

    public function responseFor(mixed $actor, mixed $subject): ?Response;

    public function reactionFor(mixed $actor, mixed $subject, ?string $reactionType = null): ?Reaction;

    public function subscriptionsFor(mixed $subscriber, mixed $subject = null): iterable;

    public function remindersFor(mixed $recipient, mixed $subject): iterable;
}
```

### `EngagementCounterService`

```php
interface EngagementCounterService
{
    public function countFollowers(mixed $subject): int;

    public function countBookmarks(mixed $subject): int;

    public function countResponses(mixed $subject, ?string $responseType = null): int;

    public function countReactions(mixed $subject, ?string $reactionType = null): int;

    public function recalculate(mixed $subject): void;
}
```

### `EngagementPolicyResolver`

```php
interface EngagementPolicyResolver
{
    public function canFollow(mixed $actor, mixed $subject): bool;

    public function canBookmark(mixed $actor, mixed $subject): bool;

    public function canRespond(mixed $actor, mixed $subject, string $responseType): bool;

    public function canReact(mixed $actor, mixed $subject, string $reactionType): bool;

    public function canSubscribe(mixed $actor, mixed $subject = null, string $subscriptionType = 'updates'): bool;

    public function canSetReminder(mixed $actor, mixed $subject, string $reminderType): bool;
}
```

## Laravel events emitted

The package must dispatch Laravel events for integration.

```text
FollowCreated
FollowMuted
FollowUnmuted
FollowRemoved

BookmarkCreated
BookmarkRemoved
BookmarkArchived
BookmarkAddedToCollection
BookmarkRemovedFromCollection

ResponseCreated
ResponseChanged
ResponseCancelled

ReactionCreated
ReactionRemoved

SubscriptionCreated
SubscriptionMuted
SubscriptionUnmuted
SubscriptionCancelled
SubscriptionMatched

ReminderCreated
ReminderScheduled
ReminderDue
ReminderSent
ReminderCancelled
ReminderFailed
```

## Integration listeners

Other packages may listen:

```text
Analytics package:
- FollowCreated -> record metric
- BookmarkCreated -> record metric
- ResponseCreated -> record conversion/intent metric

Events package:
- ResponseCreated going for EventOccurrence -> maybe show going count
- SubscriptionMatched for EventUpdate -> resolve notification audience

Laravel Notifications:
- ReminderDue -> send Notification
- SubscriptionMatched -> send Notification according to host app rules
```

## Null adapters

Host packages should be able to depend on Interactions contracts without hard failure.

Events package should bind its own null adapter when Interactions is absent:

```php
class NullEventEngagementManager implements EventEngagementManager
{
    public function respond(mixed $actor, mixed $target, string $response): void {}
    public function isBookmarked(mixed $actor, mixed $target): bool { return false; }
    public function isFollowing(mixed $actor, mixed $target): bool { return false; }
}
```

Interactions package provides real adapter:

```php
class EngagementEventEngagementManager implements EventEngagementManager
{
    public function respond(mixed $actor, mixed $target, string $response): void
    {
        app(EngagementManager::class)->respond($actor, $target, $response);
    }
}
```

## `class_exists()` integration

Events package service provider may do:

```php
if (class_exists(\AiArmada\Engagement\EngagementServiceProvider::class)) {
    $this->app->bind(
        \AiArmada\Events\Contracts\EventEngagementManager::class,
        \AiArmada\Events\Integrations\Engagement\EngagementEventEngagementManager::class,
    );
}
```

The Interactions package may expose bridge classes, but the Events package must not require them at install time unless declared optional.

## Hooks

Provide hooks so host apps can customize behavior:

```php
Engagement::beforeFollow(fn ($actor, $subject, $options) => ...);
Engagement::afterFollow(fn (Follow $follow) => ...);
Engagement::beforeRespond(fn ($actor, $subject, $type, $options) => ...);
Engagement::afterRespond(fn (Response $response) => ...);
Engagement::resolveAllowedResponsesUsing(fn ($subject) => ...);
Engagement::resolveSubscriptionMatchesUsing(fn ($subscription, $subject, $trigger) => ...);
```

Hooks must not replace service contracts, but they allow host app customization without forking.

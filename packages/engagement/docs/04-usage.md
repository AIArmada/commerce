---
title: Usage
---

All engagement actions go through service contracts. Bind your own implementation to customize behavior.

## Following

```php
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Traits\CanFollow;
use AIArmada\Engagement\Traits\HasFollowers;

// Actor model uses CanFollow trait
class User extends Model { use CanFollow; }

// Subject model uses HasFollowers trait
class Speaker extends Model { use HasFollowers; }

// Follow a speaker
$follow = app(EngagementManager::class)->follow($user, $speaker);

// Unfollow
app(EngagementManager::class)->unfollow($user, $speaker);

// Mute/unmute notifications from a follow
app(EngagementManager::class)->muteFollow($user, $speaker);
app(EngagementManager::class)->unmuteFollow($user, $speaker);

// Check state
$resolver = app(EngagementStateResolver::class);
$resolver->isFollowing($user, $speaker);  // bool
```

Follow statuses: `active`, `muted`, `unfollowed`, `blocked`. The package never deletes rows — it transitions statuses.

## Bookmarking

```php
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Models\BookmarkCollection;
use AIArmada\Engagement\Traits\CanBookmark;
use AIArmada\Engagement\Traits\HasBookmarks;
use AIArmada\CommerceSupport\Support\OwnerContext;

class User extends Model { use CanBookmark; }
class Event extends Model { use HasBookmarks; }

// Bookmark an event
$bookmark = app(EngagementManager::class)->bookmark($user, $event);

// Remove bookmark
app(EngagementManager::class)->removeBookmark($user, $event);

// Archive bookmark
app(EngagementManager::class)->archiveBookmark($user, $event);

// Create collections
$collection = OwnerContext::withOwner($tenant, fn () => BookmarkCollection::create([
    'name' => 'Tech Events 2026',
    'visibility' => 'private',
]));

// Organize bookmarks into collections
app(EngagementManager::class)->addBookmarkToCollection($user, $bookmark, $collection);
app(EngagementManager::class)->removeBookmarkFromCollection($user, $bookmark, $collection);
```

## Responding (RSVP)

```php
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Traits\CanRespond;
use AIArmada\Engagement\Traits\HasResponses;

class User extends Model { use CanRespond; }
class Occurrence extends Model { use HasResponses; }

// RSVP with a response type
app(EngagementManager::class)->respond($user, $occurrence, 'going');

// Change response
app(EngagementManager::class)->respond($user, $occurrence, 'maybe');

// Cancel response
app(EngagementManager::class)->cancelResponse($user, $occurrence);

// Check current response
$response = $resolver->responseFor($user, $occurrence);
```

Common response types: `interested`, `going`, `maybe`, `not_going`. The response type is a free-form string — define your own enum or constants.

## Reactions

```php
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Traits\CanReact;
use AIArmada\Engagement\Traits\HasReactions;

class User extends Model { use CanReact; }
class Recording extends Model { use HasReactions; }

// React to a recording
app(EngagementManager::class)->react($user, $recording, 'like');

// Remove reaction
app(EngagementManager::class)->removeReaction($user, $recording);

// Remove specific reaction type
app(EngagementManager::class)->removeReaction($user, $recording, 'like');

// Check current reaction
$reaction = $resolver->reactionFor($user, $recording);
```

Common reaction types: `like`, `love`, `useful`, `support`, `insightful`, `funny`. Free-form string.

## Subscriptions

```php
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Traits\CanSubscribe;
use AIArmada\Engagement\Traits\HasSubscriptions;

class User extends Model { use CanSubscribe; }

// Subscribe to all online events
$subscription = app(SubscriptionManager::class)->subscribe(
    subscriber: $user,
    subject: null,              // null = global subscription
    subscriptionType: 'updates',
    criteria: [
        'delivery_mode' => 'online',
        'visibility' => 'public',
    ],
);

// Subscribe to a specific event's updates
$subscription = app(SubscriptionManager::class)->subscribe(
    subscriber: $user,
    subject: $event,
    subscriptionType: 'updates',
);

// Unsubscribe
app(SubscriptionManager::class)->unsubscribe($user, $event);

// Mute a subscription
app(SubscriptionManager::class)->muteSubscription($subscription);

// Find subscriptions matching a subject (used by the matching engine)
$subscriptions = app(SubscriptionManager::class)->matchingSubscriptions(
    subject: $newEvent,
    trigger: 'event_published',
);
```

The `engagement:match-subscriptions` command processes subscriptions against matching subjects. Use this for content-based notification workflows.

## Reminders

```php
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Traits\CanSetReminders;
use AIArmada\Engagement\Traits\HasReminders;

class User extends Model { use CanSetReminders; }
class Occurrence extends Model { use HasReminders; }

// Set a reminder
$reminder = app(ReminderManager::class)->setReminder(
    recipient: $user,
    subject: $occurrence,
    reminderType: 'before_start',
    options: [
        'offset_minutes' => 60,
        'channels' => ['mail', 'database'],
    ],
);

// Cancel reminder
app(ReminderManager::class)->cancelReminder($user, $occurrence, 'before_start');

// The engagement:send-due-reminders command processes due reminders
$due = app(ReminderManager::class)->dueReminders();
```

Schedule `engagement:send-due-reminders` in your console kernel to deliver pending reminders.

## Sharing

```php
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Traits\CanShare;
use AIArmada\Engagement\Traits\HasShares;

class User extends Model { use CanShare; }
class Event extends Model { use HasShares; }

// Share an event to WhatsApp
$share = app(EngagementManager::class)->share($user, $event, [
    'channel' => 'whatsapp',
]);

// Share with a custom message
$share = app(EngagementManager::class)->share($user, $event, [
    'channel' => 'telegram',
    'message' => 'Check this out!',
]);
```

Share channels are free-form. Common values: `whatsapp`, `telegram`, `email`, `twitter`, `facebook`, `copy_link`.

## Working with engagement counters

```php
use AIArmada\Engagement\Contracts\EngagementCounterService;

$counter = app(EngagementCounterService::class);

$counter->countFollowers($event);    // int
$counter->countBookmarks($event);    // int
$counter->countResponses($event);    // int
$counter->countResponses($event, 'going'); // Filter by response type
$counter->countReactions($event);    // int
$counter->countReactions($event, 'like'); // Filter by reaction type

// Recalculate all counters for a subject
$counter->recalculate($event);
```

Counters can be cached via the `EngagementCounter` model for performant display.

## Working with the state resolver

```php
use AIArmada\Engagement\Contracts\EngagementStateResolver;

$resolver = app(EngagementStateResolver::class);

$isFollowing = $resolver->isFollowing($user, $speaker);
$isBookmarked = $resolver->isBookmarked($user, $event);
$response = $resolver->responseFor($user, $occurrence);
$reaction = $resolver->reactionFor($user, $recording);
$subscriptions = $resolver->subscriptionsFor($user, $event);
$reminders = $resolver->remindersFor($user, $occurrence);
```

## Events package integration

When `aiarmada/events` is installed, the engagement package auto-registers an `EventEngagementManager` that connects engagement actions to event domain events. The bridge delegates all actions — `follow`, `bookmark`, `respond`, `subscribe`, `remind`, and `share` — through the engagement package's `EngagementManager`, creating proper persisted records with lifecycle events.

```php
// Sharing an event persists a Share record via EngagementManager
app(EngagementManager::class)->share($user, $event, ['channel' => 'twitter']);
// → Share record created with status 'created', then 'shared'
// → ShareCreated and ShareCompleted dispatched
```

The `stateFor()` method includes a `share` key with the active share's `id`, `share_url`, `share_token`, `channel`, `status`, and `shared_at`.

To support rich share metadata, implement the `Shareable` contract on your event subject models, or add duck-type methods (`shareUrl()`, `shareTitle()`, `shareDescription()`, `shareImage()`). The `Event` model already includes these duck-type methods.

```php
$event->shareUrl();        // Generated from configured route
$event->shareTitle();      // The event title
$event->shareDescription(); // The event summary
$event->shareImage();      // First media URL
```

## Customizing via contracts

Every engagement service has a contract. Bind your own implementation to override behavior:

```php
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;

app()->bind(EngagementPolicyResolver::class, MyPolicyResolver::class);
```

Policy resolver methods are enforced. Returning `false` causes the corresponding
operation to throw `AuthorizationException`.

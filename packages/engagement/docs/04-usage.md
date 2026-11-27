---
title: Usage
---

All engagement actions go through service contracts. Bind your own implementation to customize behavior.

## Model contracts

Manager boundaries are intentionally typed. Actor models must implement `CanInteract`; subject models must implement the marker contract for the operation they support. The marker interfaces describe the data needed by notifications and policy resolution, while the model itself supplies the Eloquent identity used for persistence.

```php
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Followable;

class User extends Model implements CanInteract
{
    public function interactionDisplayName(): string { return $this->name; }

    public function interactionNotificationRoute(?string $channel = null): mixed
    {
        return $this->routeNotificationFor($channel);
    }
}

class Speaker extends Model implements Followable
{
    public function followableName(): string { return $this->name; }
    public function followableUrl(): ?string { return route('speakers.show', $this); }
    public function followableImage(): ?string { return null; }
    public function defaultFollowNotificationLevel(): ?string { return 'all'; }
}
```

Passing a model that does not implement the required marker fails at the manager signature instead of failing during morph resolution.

## Following

```php
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Features\Interactions\Concerns\CanFollow;
use AIArmada\Engagement\Features\Interactions\Concerns\HasFollowers;

// Actor model implements CanInteract and uses CanFollow
class User extends Model implements CanInteract { use CanFollow; }

// Subject model implements Followable and uses HasFollowers
class Speaker extends Model implements Followable { use HasFollowers; }

// Follow a speaker
$follow = app(ManageInteractionsAction::class)->follow($user, $speaker);

// Unfollow
app(ManageInteractionsAction::class)->unfollow($user, $speaker);

// Mute/unmute notifications from a follow
app(ManageInteractionsAction::class)->muteFollow($user, $speaker);
app(ManageInteractionsAction::class)->unmuteFollow($user, $speaker);

// Check state
$resolver = app(ReadsInteractionState::class);
$resolver->isFollowing($user, $speaker);  // bool
```

Follow statuses: `active`, `muted`, `unfollowed`, `blocked`. The package never deletes rows — it transitions statuses.

## Bookmarking

```php
use AIArmada\Engagement\Contracts\Bookmarkable;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Models\BookmarkCollection;
use AIArmada\Engagement\Features\Interactions\Concerns\CanBookmark;
use AIArmada\Engagement\Features\Interactions\Concerns\HasBookmarks;
use AIArmada\CommerceSupport\Support\OwnerContext;

class User extends Model implements CanInteract { use CanBookmark; }
class Event extends Model implements Bookmarkable { use HasBookmarks; }

// Bookmark an event
$bookmark = app(ManageInteractionsAction::class)->bookmark($user, $event);

// Remove bookmark
app(ManageInteractionsAction::class)->removeBookmark($user, $event);

// Archive bookmark
app(ManageInteractionsAction::class)->archiveBookmark($user, $event);

// Create collections
$collection = OwnerContext::withOwner($tenant, fn () => BookmarkCollection::create([
    'name' => 'Tech Events 2026',
    'visibility' => 'private',
]));

// Organize bookmarks into collections
app(ManageInteractionsAction::class)->addBookmarkToCollection($user, $bookmark, $collection);
app(ManageInteractionsAction::class)->removeBookmarkFromCollection($user, $bookmark, $collection);
```

## Responding (RSVP)

```php
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Features\Interactions\Concerns\CanRespond;
use AIArmada\Engagement\Features\Interactions\Concerns\HasResponses;

class User extends Model implements CanInteract { use CanRespond; }
class Occurrence extends Model implements Respondable { use HasResponses; }

// RSVP with a response type
app(ManageInteractionsAction::class)->respond($user, $occurrence, 'going');

// Change response
app(ManageInteractionsAction::class)->respond($user, $occurrence, 'maybe');

// Cancel response
app(ManageInteractionsAction::class)->cancelResponse($user, $occurrence);

// Check current response
$response = app(ReadsInteractionState::class)->responseFor($user, $occurrence);
```

Common response types: `interested`, `going`, `maybe`, `not_going`. The response type is a free-form string — define your own enum or constants.

## Reactions

```php
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Contracts\Reactable;
use AIArmada\Engagement\Features\Interactions\Concerns\CanReact;
use AIArmada\Engagement\Features\Interactions\Concerns\HasReactions;

class User extends Model implements CanInteract { use CanReact; }
class Recording extends Model implements Reactable { use HasReactions; }

// React to a recording
app(ManageInteractionsAction::class)->react($user, $recording, 'like');

// Remove reaction
app(ManageInteractionsAction::class)->removeReaction($user, $recording);

// Remove specific reaction type
app(ManageInteractionsAction::class)->removeReaction($user, $recording, 'like');

// Check current reaction
$reaction = app(ReadsInteractionState::class)->reactionFor($user, $recording);
```

Common reaction types: `like`, `love`, `useful`, `support`, `insightful`, `funny`. Free-form string.

## Subscriptions

```php
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Subscribable;
use AIArmada\Engagement\Features\Subscriptions\Actions\ManageSubscriptionsAction;
use AIArmada\Engagement\Features\Subscriptions\Concerns\CanSubscribe;
use AIArmada\Engagement\Features\Subscriptions\Concerns\HasSubscriptions;

class User extends Model implements CanInteract { use CanSubscribe; }
class Event extends Model implements Subscribable { use HasSubscriptions; }

// Subscribe to all online events
$subscription = app(ManageSubscriptionsAction::class)->subscribe(
    subscriber: $user,
    subject: null,              // null = global subscription
    subscriptionType: 'updates',
    criteria: [
        'delivery_mode' => 'online',
        'visibility' => 'public',
    ],
);

// Subscribe to a specific event's updates
$subscription = app(ManageSubscriptionsAction::class)->subscribe(
    subscriber: $user,
    subject: $event,
    subscriptionType: 'updates',
);

// Unsubscribe
app(ManageSubscriptionsAction::class)->unsubscribe($user, $event);

// Mute a subscription
app(ManageSubscriptionsAction::class)->muteSubscription($subscription);

// Find subscriptions matching a subject (used by the matching engine)
$subscriptions = app(ManageSubscriptionsAction::class)->matchingSubscriptions(
    subject: $newEvent,
    trigger: 'event_published',
);
```

The `engagement:match-subscriptions` command processes subscriptions against matching subjects. Use this for content-based notification workflows.

Subscribing is idempotent per subscriber, subject, type, and criteria: repeating the same call returns the existing row (reactivating it when it was muted or unsubscribed), while different criteria create separate rows. Criteria are matched by a normalized hash, so key order does not matter.

The match command only exposes `subject_type` and `subject_id` to criteria matching. Subjects that want attribute-based criteria must opt in explicitly:

```php
use AIArmada\Engagement\Contracts\HasSubscriptionMatchContext;

class Event extends Model implements HasSubscriptionMatchContext, Subscribable
{
    /** @return array<string, mixed> */
    public function subscriptionMatchContext(): array
    {
        return ['delivery_mode' => $this->delivery_mode];
    }
}
```

## Reminders

```php
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Remindable;
use AIArmada\Engagement\Features\Reminders\Actions\ManageRemindersAction;
use AIArmada\Engagement\Features\Reminders\Concerns\CanSetReminders;
use AIArmada\Engagement\Features\Reminders\Concerns\HasReminders;

class User extends Model implements CanInteract { use CanSetReminders; }
class Occurrence extends Model implements Remindable { use HasReminders; }

// Set a reminder for an absolute time
$reminder = app(ManageRemindersAction::class)->setReminder(
    recipient: $user,
    subject: $occurrence,
    reminderType: 'before_start',
    options: [
        'remind_at' => now()->addDay(),
        'channel' => 'mail',
    ],
);

// Or schedule it relative to a subject anchor (resolved immediately via
// Remindable::reminderAnchorTime() minus the offset)
$reminder = app(ManageRemindersAction::class)->setReminder(
    recipient: $user,
    subject: $occurrence,
    reminderType: 'before_start',
    options: [
        'offset_minutes' => 60,
        'anchor_type' => 'starts_at',
        'channel' => 'mail',
    ],
);

// Cancel reminder
app(ManageRemindersAction::class)->cancelReminder($user, $occurrence, 'before_start');

// The engagement:send-due-reminders command detects due reminders
$due = app(ManageRemindersAction::class)->dueReminders();
```

Schedule `engagement:send-due-reminders` in your console kernel. Engagement owns due detection and the `ReminderDue`/`ReminderSent` lifecycle; when `aiarmada/communications` is installed, the due event is delivered through `DispatchManagedNotificationAction` and receives an `engagement_reminder` communication reference. Without communications, host applications may provide their own `ReminderDue` listener.

Each due reminder is reloaded with a row lock while `ReminderDue` is dispatched,
so overlapping command runs skip reminders already claimed or completed. Direct
`markSent` and `markFailed` calls only transition pending or scheduled reminders;
terminal reminders are left unchanged. A dispatch failure marks that reminder
`failed` with the reason and the command continues with the next one.

Setting the same pending reminder twice (same recipient, subject, type, channel,
and instant) returns the existing row instead of creating a duplicate.

Provide either `remind_at` or `offset_minutes` (with `anchor_type`), never both.
An offset whose anchor does not resolve, or an offset without an anchor, throws
an `InvalidArgumentException` instead of storing a reminder that could never fire.

Per-reminder notification classes go through the `notifications.allowed`
allowlist; unlisted classes are rejected at `setReminder` time and again if they
ever reach the dispatch listener.

## Sharing

```php
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Features\Interactions\Actions\ManageInteractionsAction;
use AIArmada\Engagement\Contracts\Shareable;
use AIArmada\Engagement\Features\Interactions\Concerns\CanShare;
use AIArmada\Engagement\Features\Interactions\Concerns\HasShares;

class User extends Model implements CanInteract { use CanShare; }
class Event extends Model implements Shareable { use HasShares; }

// Share an event to WhatsApp
$share = app(ManageInteractionsAction::class)->share($user, $event, [
    'channel' => 'whatsapp',
]);

// Share with a custom message
$share = app(ManageInteractionsAction::class)->share($user, $event, [
    'channel' => 'telegram',
    'message' => 'Check this out!',
]);
```

Share channels are free-form. Common values: `whatsapp`, `telegram`, `email`, `twitter`, `facebook`, `copy_link`.

Share creation is authorized through `EngagementPolicyResolver::canShare()`, like every other primitive. The stored `share_token` is the same token embedded in `share_url` (generated once per share); pass `token` explicitly to reuse an existing token.

## Concurrent engagement writes

Follow, bookmark, response, reaction, subscription, and collection-item identity
writes run inside transactions and lock an existing identity before restoring or
returning it. The package's owner-aware unique indexes are the final race guard;
if two requests create the same identity concurrently, the losing request
returns the committed record instead of emitting a second lifecycle event or
counter update.
Actor, subject, and owner identity values use byte-exact comparisons across
supported databases; case and trailing spaces in polymorphic type values are
significant.

## Working with engagement counters

```php
use AIArmada\Engagement\Features\Counters\Actions\UpdateEngagementCountersAction;

$counter = app(UpdateEngagementCountersAction::class);

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
All manager writes update the affected cached counters synchronously inside the
same transaction, as atomic increments/decrements per lifecycle event (no
per-write recounts). Direct model writes and imports bypass those lifecycle
events; schedule `engagement:reconcile-counters` at least hourly as the repair
cadence. The reconcile command iterates owners explicitly, so it runs correctly
from console cron without an ambient owner.

## Working with the state resolver

```php
use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Features\Subscriptions\Api\ReadsSubscriptionState;
use AIArmada\Engagement\Features\Reminders\Api\ReadsReminderState;

$resolver = app(ReadsInteractionState::class);

$isFollowing = $resolver->isFollowing($user, $speaker);
$isBookmarked = $resolver->isBookmarked($user, $event);
$response = $resolver->responseFor($user, $occurrence);
$reaction = $resolver->reactionFor($user, $recording);
$subscriptions = app(ReadsSubscriptionState::class)->subscriptionsFor($user, $event);
$reminders = app(ReadsReminderState::class)->remindersFor($user, $occurrence);
```

## Events package adapter and attendance separation

When `aiarmada/events` is installed, the engagement package can bind an `EventEngagementManager` adapter for explicit calls. Event registration remains transactional attendance intent; engagement responses remain social-graph intent. The package does not automatically translate an `Interested` registration or an event-publication event into an engagement response or subscription match.

Use `engagement:match-subscriptions` explicitly for content-based matching. The subject model supplied to that command must implement `Subscribable`.

```php
// Sharing a compatible event subject persists a Share record via ManageInteractionsAction
app(ManageInteractionsAction::class)->share($user, $event, ['channel' => 'twitter']);
// → Share record created with status 'created', then 'shared'
// → ShareCreated and ShareCompleted dispatched
```

The `stateFor()` method includes a `share` key with the active share's `id`, `share_url`, `share_token`, `channel`, `status`, and `shared_at`.

To support rich share metadata, implement the `Shareable` contract on your event subject models. The typed manager does not use duck-typed share methods as a substitute for the contract.

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

The default resolver is intentionally permissive: it is a host seam, not an
authorization decision. Tenant isolation does not come from the resolver — it
comes from the `commerce-support` owner boundary (`HasOwner`, `OwnerScope`,
`OwnerWriteGuard`, `OwnerContext`) applied to every engagement row. Hosts that
need per-actor authorization bind a strict `EngagementPolicyResolver`; hosts
that rely on tenancy alone keep the default.

Free-form inputs (types, channels, notes, messages, destinations, metadata) are
length-validated at the manager boundaries: 255 characters for `string` columns,
65,535 for `text` columns. Overlong or mistyped values throw
`InvalidArgumentException` before any policy check or write.

---
title: Usage
---

# Usage

## Basic usage

### Recording a notification (native mode)

The package automatically observes `NotificationSending` and `NotificationSent` events when `native_capture` is enabled. No code changes needed.

When `auto_capture` is enabled, unknown notification classes are rejected by
default. Opt a notification in by extending `BaseCommunicationNotification`
and returning both existing enum values:

```php
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationTrigger;
use AIArmada\Communications\Notifications\BaseCommunicationNotification;

final class InvoicePaid extends BaseCommunicationNotification
{
    public function notificationFamily(): ?NotificationFamily
    {
        return NotificationFamily::PaymentReceived;
    }

    public function notificationTrigger(): ?NotificationTrigger
    {
        return NotificationTrigger::PaymentCompleted;
    }
}
```

Alternatively, add the notification class to
`communications.features.auto_capture_allowlist`. The denylist always wins.
Channels listed in `communications.features.auto_capture_ignored_channels`
are skipped. The listener also guards against re-entrant capture through
`AutoCaptureState`, so recording a notification never triggers an infinite
capture loop.

### Managed notification

```php
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Facades\Communications;

$context = CommunicationContextData::from([
    'purpose' => 'invoice-paid',
    'category' => 'transactional',
    'subjectType' => $invoice::class,
    'subjectId' => (string) $invoice->getKey(),
]);

Communications::notify($user, new InvoicePaid($invoice), $context);
```

### Using the context trait on notifications

```php
use AIArmada\Communications\Notifications\BaseCommunicationNotification;

class InvoicePaid extends BaseCommunicationNotification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invoice Paid')
            ->line('Your invoice has been paid.');
    }
}
```

The `HasCommunicationContext` context carries scalar identifiers only (a
`communicationId` plus a `deliveryIdsByChannel` map), keeping notifications
serialization-safe for queued dispatch.

## Inbox notifications

```php
use AIArmada\Communications\Features\Inbox\Actions\DispatchInboxNotificationAction;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;

app(DispatchInboxNotificationAction::class)->handle(
    recipient: $user,
    title: 'System maintenance scheduled',
    body: 'The platform will be offline from 01:00 to 02:00 UTC.',
    family: NotificationFamily::SystemAnnouncement,
    priority: NotificationPriority::Normal,
    trigger: NotificationTrigger::ManualDispatch,
);
```

`DispatchInboxNotificationAction` creates the communication record and the inbox row together.

### Reading inbox entries on a model

```php
use AIArmada\Communications\Features\Inbox\Traits\HasInbox;
use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    use HasInbox;
}

$unreadCount = $user->unreadCount();
$user->markAsRead((string) $inboxId);
$user->markAllAsRead();
$user->archiveRead();
```

`HasInbox` exposes the recipient inbox relation, unread counts, and read/archive helpers.

### Live inbox screen

The package registers the `communications.inbox-index` Livewire component for the inbox listing surface.

## Destinations

Store channel addresses on notifiables when you need explicit routing (multiple emails, verified phones, or provider external ids):

```php
use AIArmada\Communications\Features\RecipientRouting\Actions\SaveRecipientDestination;

$destination = app(SaveRecipientDestination::class)->create($user, [
    'channel' => 'mail',
    'address' => 'billing@example.com',
    'is_primary' => true,
    'verified_at' => now(),
]);
```

New destinations default to `active` status. Pass the explicit `status` argument to the action when changing the status; recipient identity is associated from the persisted model.

Resolution order for the default `CommunicationDestinationResolver`:

1. Active `CommunicationDestination` for the recipient morph + channel, ordered by `is_primary` then `verified_at`
2. Notifiable routing via `routeNotificationFor($channel)` or `routeNotificationFor{Driver}()`

Resolved values are encrypted, hashed, and hinted for delivery storage through
the destination protector. The source `CommunicationDestination.address` value
is encrypted at rest through the model cast and is stored in a text column.
Existing development databases are reset rather than backfilled when this
breaking storage change is applied.

## Preferences

Preferences may optionally narrow to a scope:

```php
use AIArmada\Communications\Features\RecipientRouting\Actions\SaveRecipientPreference;

$preference = app(SaveRecipientPreference::class)->create($user, [
    'channel' => 'mail',
    'category' => 'marketing',
    'scope_type' => 'event',
    'scope_key' => (string) $event->getKey(),
]);
```

Leave `scope_type` and `scope_key` null for recipient-wide channel/category preferences.

## Working with templates

Templates are two-level: a `CommunicationTemplate` holds the stable identity, and
`CommunicationTemplateVersion` holds each revision. Both `status` and the `*_type`
discriminators are excluded from `$fillable`, and `communication_templates.status`
has no database default, so set `status` directly on the model:

```php
use AIArmada\Communications\Features\Templates\Actions\PublishTemplateAction;
use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Features\Templates\Models\CommunicationTemplate;

$template = CommunicationTemplate::query()->make([
    'key' => 'invoice-paid',
    'name' => 'Invoice paid',
    'category' => 'transactional',
    'default_locale' => 'en',
]);
$template->status = TemplateStatus::Draft;
$template->save();

$version = $template->versions()->create([
    'version' => 1,
    'channel' => 'mail',
    'locale' => 'en',
    'subject' => 'Invoice {{ invoice_number }} paid',
    'content_text' => 'Thanks, we received your payment.',
    // `checksum` is NOT NULL and is not generated by the model — pass a stable hash.
    'checksum' => hash('sha256', 'invoice-paid:1:mail:en'),
]);

app(PublishTemplateAction::class)->handle((string) $version->id);
```

`PublishTemplateAction` stamps `published_at` on the version, flips the template to
`TemplateStatus::Published`, and throws if the version is already published.

Rendering a template into a content snapshot (`RenderedContentData`):

```php
use AIArmada\Communications\Features\Aggregate\Actions\RenderCommunicationContentAction;

$rendered = app(RenderCommunicationContentAction::class)->handle(
    $template->id,
    'mail',
    'en',
    ['invoice_number' => 'INV-1042'],
);
```

## Creating a communication

```php
use AIArmada\Communications\Features\Aggregate\Actions\CreateCommunicationAction;
use AIArmada\Communications\Data\CommunicationContextData;

$communicationId = app(CreateCommunicationAction::class)->handle(
    CommunicationContextData::from([
        'purpose' => 'invoice-paid',
        'category' => 'transactional',
        'subjectType' => $invoice::class,
        'subjectId' => (string) $invoice->getKey(),
    ]),
);
```

## Console commands

```bash
# Dispatch scheduled communications
php artisan communications:dispatch-due

# Prune old communication data
php artisan communications:prune

# Expire communications past deadline
php artisan communications:expire

# Reconcile aggregate statuses
php artisan communications:reconcile

# Prune archived inbox rows for the explicit global scope
php artisan communications:prune-inboxes

# Prune archived inbox rows for one owner
php artisan communications:prune-inboxes --owner="App\Models\Team:1"

# Prune archived inbox rows for every owner scope and global rows
php artisan communications:prune-inboxes --all-owners

# Replay stored provider webhook events
php artisan communications:replay-webhooks
```

Pruning hard-deletes eligible records; communications models use no soft deletes.
Use `--owner` only when owner scoping is enabled, and choose either `--owner` or `--all-owners` for each invocation.

## Owner scoping

All tenant-owned queries are automatically scoped to the current owner when `communications.features.owner.enabled` is true. Use `OwnerContext::withOwner()` for scoped operations. Inbox records follow the same owner boundary as the rest of the communications data.

Webhook payloads cannot select an owner. Bind `AIArmada\Communications\Contracts\WebhookOwnerResolver` to return a trusted `AIArmada\CommerceSupport\Data\RecordReference` (owner morph type and ID) from provider-authenticated payload data. Explicit owner references are resolved again in the worker and missing owners fail closed. Returning `null` lets provider events derive the delivery's owner scope from the delivery itself.

## Thread resolution

`ResolveCommunicationThreadAction` resolves and writes only the partition the
thread will actually persist to:

- With owner scoping enabled and `auto_assign_on_create` enabled, an owned
  context resolves that owner's threads. An explicit global context resolves
  global threads.
- With `auto_assign_on_create` disabled, owned and global contexts both write
  the global partition; an owned context cannot update an existing global
  thread without an explicit global context.
- With owner scoping disabled, thread resolution writes the global partition.

External thread ids are unique per exact owner tuple and separately per global
partition, so the same provider thread may exist once for each owner and once
globally. Null external ids are not unique and may repeat. `include_global`
does not merge partitions during writes: an owned caller never adopts or
mutates a global thread.

PostgreSQL `REPEATABLE READ` callers that lose a concurrent insert race cannot
see the committed winner inside their snapshot. The action rethrows
`UniqueConstraintViolationException` in that case; retry the caller transaction
to resolve against the winner.

## Restricted attributes

`status` and polymorphic `*_type` discriminators are intentionally absent from
`$fillable` on communications models. Set them via direct assignment or
`forceFill()` — passing them to `create()`/`update()` silently drops the keys.

## Eligibility contracts

Consent, preferences, quiet hours and rate limits resolve through `Drivers\Eligibility\PermissiveEligibilityResolver`; active persisted suppressions resolve through `ActiveSuppressionResolver`. Custom resolvers implement `resolveConsent()` (`ConsentResolver`) and `resolveSuppression()` (`SuppressionResolver`).

Webhooks are allowlisted per provider (`communications.webhooks.providers`), HMAC-signed (configurable header and algorithm, default `X-Webhook-Signature` with SHA-256 over the exact timestamp header, a literal `.` and the raw body), timestamp-bound (`X-Webhook-Timestamp`, default 300s tolerance), and rate-limited (`throttle:communications-webhooks`). Unconfigured providers fail closed — see `03-configuration.md`.

## Tracking tokens

```php
use AIArmada\Communications\Features\Tracking\Actions\CreateTrackingTokenAction;
use AIArmada\Communications\Features\Tracking\Actions\RecordTrackingInteractionAction;
use AIArmada\Communications\Enums\TrackingInteractionType;

$created = app(CreateTrackingTokenAction::class)->handle(
    deliveryId: $delivery->id,
    kind: 'click',
    targetUrl: 'https://example.com/welcome',
);

// Only the hash is persisted. Use the returned plaintext token when
// building the tracking URL; it cannot be recovered afterwards.
$url = 'https://example.com/t/' . $created->plaintextToken;

$eventId = app(RecordTrackingInteractionAction::class)->handle(
    tokenId: $created->trackingTokenId,
    interactionType: TrackingInteractionType::Click,
);
```

Target URLs must be absolute `http(s)` URLs and are encrypted at rest via
`DestinationProtector` (Laravel authenticated encryption). Expired or revoked
tokens reject new interactions, and interaction types are constrained to
`TrackingInteractionType` (`open`, `click`).

## Delivery state machine

`TransitionDeliveryAction` is the only legal way to move a delivery between
statuses. Provider webhook events are applied through the same action:
out-of-order provider updates jump forward explicitly and dispatch the usual
`Delivery*` domain events, while late events for terminal deliveries
(`failed`, `cancelled`, `expired`, `suppressed`, `unsubscribed`) are recorded
without regressing the delivery status.

Expiring a communication dispatches `CommunicationExpired` and expires its
pending deliveries; the original `expires_at` deadline is preserved.

Legal transitions come from a single `ALLOWED_TRANSITIONS` table on the
action; anything else throws unless the `force` flag is passed. Provider
statuses follow an explicit progress precedence (`pending` through `replied`)
plus terminal failure handling, so a late `accepted` event after `sent` keeps
`sent` while still stamping `accepted_at`. Each transition stamps its `*_at`
column only when it is still null, so lifecycle timestamps are never cleared
or overwritten.

Aggregate status is derived by `RecalculateCommunicationStatusAction`: all
deliveries terminal with no failures or cancellations means `Completed`;
terminal with failures but no success means `Failed`; terminal with both
means `PartiallyCompleted`; terminal with cancellations but no successes or
failures means `Cancelled`; anything else stays `Processing`. Only the
`Completed` and `Failed` outcomes dispatch `CommunicationCompleted` /
`CommunicationFailed`, and first entry stamps `completed_at` / `failed_at`.
After mass delivery changes, run `php artisan communications:reconcile` to
resynchronize aggregates.

- Managed notification dispatch (`CommunicationManager::notify()` / `Communications::notify()`) accepts `CommunicationContextData` or null and returns the persisted communication ID as a string. Pass event morph type and ID in the context instead of passing an Eloquent model or an untyped array.

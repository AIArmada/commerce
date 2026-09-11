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

## Inbox notifications

```php
use AIArmada\Communications\Actions\DispatchInboxNotificationAction;
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
use AIArmada\Communications\Traits\HasInbox;
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
use AIArmada\Communications\Models\CommunicationDestination;

CommunicationDestination::query()->create([
    'recipient_type' => $user->getMorphClass(),
    'recipient_id' => (string) $user->getKey(),
    'channel' => 'mail',
    'address' => 'billing@example.com',
    'status' => 'active',
    'is_primary' => true,
    'verified_at' => now(),
]);
```

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
use AIArmada\Communications\Models\CommunicationPreference;

CommunicationPreference::query()->create([
    'recipient_type' => $user->getMorphClass(),
    'recipient_id' => (string) $user->getKey(),
    'channel' => 'mail',
    'category' => 'marketing',
    'scope_type' => 'event',
    'scope_key' => (string) $event->getKey(),
    // additional preference columns as needed
]);
```

Leave `scope_type` and `scope_key` null for recipient-wide channel/category preferences.

## Working with templates

```php
use AIArmada\Communications\Actions\CreateCommunicationAction;

$communication = app(CreateCommunicationAction::class)->handle(
    $contextData,
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

# Prune archived inbox rows
php artisan communications:prune-inboxes
```

## Owner scoping

All tenant-owned queries are automatically scoped to the current owner when `communications.features.owner.enabled` is true. Use `OwnerContext::withOwner()` for scoped operations. Inbox records follow the same owner boundary as the rest of the communications data.

Webhook payloads cannot select an owner. Bind `AIArmada\Communications\Contracts\WebhookOwnerResolver` to resolve a trusted owner model from provider-authenticated payload data. Returning `null` processes the event in explicit global context.

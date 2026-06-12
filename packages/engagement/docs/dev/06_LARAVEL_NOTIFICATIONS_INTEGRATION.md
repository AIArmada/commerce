# 06 — Laravel Notifications Integration

The user has decided that Laravel Notifications are sufficient. Therefore, do not create a separate Notifications package.

The Interactions package may store reminder/subscription preferences and dispatch events/jobs, but delivery should use Laravel Notifications or host application channels.

## What Interactions owns

```text
subscriptions
= who wants to be informed about what kind of thing

reminders
= who requested a scheduled reminder for a subject
```

## What Laravel Notifications owns

```text
mail delivery
database notifications if enabled
broadcast notifications
SMS/WhatsApp custom channels if host app registers them
read/unread notification state if using Laravel database notifications
```

## No notification delivery tables here

Do not create:

```text
notification_batches
notification_deliveries
notification_logs
notification_reads
notification_failures
```

If the host app needs database notifications, use Laravel's notifications table or the app's existing notification implementation.

## Subscription flow

Example: user subscribes to live online events.

```text
1. User creates subscription:
   subscription_type = live_online_events
   criteria.delivery_mode = online
   criteria.has_live_link = true

2. Events package publishes EventOccurrencePublished.

3. Interactions listener checks matching subscriptions.

4. For each matching subscriber, host app sends Laravel Notification.
```

## Reminder flow

Example: user asks to be reminded one hour before an event occurrence.

```text
1. User sets reminder:
   remindable = EventOccurrence
   reminder_type = before_start
   offset_minutes = 60

2. Scheduler command finds due reminders.

3. ReminderDue event is dispatched.

4. Host app sends Laravel Notification.

5. Interactions marks reminder sent_at or failed_at.
```

## Commands

Provide an artisan command:

```bash
php artisan interactions:send-due-reminders
```

Responsibilities:

- Find reminders where status in `pending` or `scheduled`.
- Resolve actual `remind_at` if needed.
- Dispatch `ReminderDue`.
- Let listeners send Laravel Notifications.
- Mark `sent_at` only after successful send event or configured handler.

Optional:

```bash
php artisan interactions:match-subscriptions --trigger=event_occurrence_published
```

Useful for replaying subscription matching.

## Notification classes

The Interactions package may ship generic notification classes, but should allow host override.

Example:

```php
class InteractionReminderNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return config('interactions.notifications.default_channels', ['mail', 'database']);
    }
}
```

But the host app should be able to bind:

```php
InteractionReminderNotification::class => App\Notifications\CustomReminderNotification::class
```

## Notification preferences

`follows`, `subscriptions`, and `reminders` may store preferences:

```json
{
  "channels": ["mail", "database"],
  "quiet_hours": {
    "start": "22:00",
    "end": "07:00",
    "timezone": "Asia/Kuala_Lumpur"
  },
  "digest": "daily"
}
```

The Interactions package should not enforce every channel itself. It should expose preferences to Laravel Notification logic.

## Events emitted

```text
SubscriptionMatched
ReminderDue
ReminderSent
ReminderFailed
```

Host app listeners can perform delivery.

## Acceptance checklist

- [x] No custom notification delivery tables are created. (verified by lint)
- [x] Laravel Notifications can be used for reminder delivery. (InteractionReminderNotification exists)
- [x] Host app can override notification class/channel. (config('interactions.reminder.default_channels'))
- [ ] Reminder command is idempotent. (not verified; no status-guard against double-send)
- [ ] Subscription matching can be replayed safely. (not verified)
- [x] Failed reminders are marked with `failed_at` and `failure_reason`. (migration columns exist)
- [x] Sent reminders are marked with `sent_at`. (migration column exists)

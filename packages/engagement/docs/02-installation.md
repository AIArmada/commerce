---
title: Installation
---

## Install

```bash
composer require aiarmada/engagement
```

## Publish and run migrations

```bash
php artisan vendor:publish --provider="AIArmada\Engagement\EngagementServiceProvider" --tag="engagement-migrations"
php artisan migrate
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\Engagement\EngagementServiceProvider" --tag="engagement-config"
```

## Schedule console commands

Add to your `routes/console.php` or `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('engagement:send-due-reminders')->everyMinute();
```

`engagement:match-subscriptions` requires a subject, so it cannot be scheduled blindly —
invoke it from an application listener or job when a subject is published:

```bash
php artisan engagement:match-subscriptions "App\Models\Event" <event-uuid> --trigger=event_published
```

`engagement:reconcile-counters` takes no arguments and is safe to schedule:

```php
Schedule::command('engagement:reconcile-counters')->hourly();
```

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `ENGAGEMENT_TABLE_PREFIX` | `engagement_` | Prefix for all engagement tables |
| `ENGAGEMENT_JSON_COLUMN_TYPE` | `jsonb` | JSON column type |
| `ENGAGEMENT_DEFAULT_FOLLOW_NOTIFICATION_LEVEL` | `all` | Default notification level for follows |
| `ENGAGEMENT_DEFAULT_RESPONSE_VISIBILITY` | `public` | Default visibility for responses |
| `ENGAGEMENT_REMINDER_BATCH_SIZE` | `100` | Reminders processed per batch |
| `ENGAGEMENT_STATE_RESULT_LIMIT` | `100` | Row cap for `subscriptionsFor()` / `remindersFor()` |
| `ENGAGEMENT_OWNER_ENABLED` | `true` | Enable owner scoping |
| `ENGAGEMENT_OWNER_INCLUDE_GLOBAL` | `false` | Include global rows in owner-scoped reads |
| `ENGAGEMENT_OWNER_AUTO_ASSIGN` | `true` | Auto-assign owner on create |
| `ENGAGEMENT_NOTIFICATION_REMINDER_CLASS` | `EngagementReminderNotification::class` | Reminder notification class |

## Adding traits to models

```php
use AIArmada\Engagement\Traits\CanFollow;
use AIArmada\Engagement\Traits\HasBookmarks;
use AIArmada\Engagement\Traits\HasFollowers;
use AIArmada\Engagement\Traits\HasReactions;

class User extends Model
{
    use CanFollow;   // This user can follow others
    use HasFollowers; // This user can be followed
}

class Event extends Model
{
    use HasFollowers; // Events can be followed
    use HasBookmarks; // Events can be bookmarked
    use HasReactions; // Events can be reacted to
}
```

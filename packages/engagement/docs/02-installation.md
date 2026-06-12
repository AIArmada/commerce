---
title: Installation
---

## Install

```bash
composer require aiarmada/engagement
```

## Publish and run migrations

```bash
php artisan vendor:publish --provider="AIArmada\Engagement\EngagementServiceProvider" --tag="migrations"
php artisan migrate
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\Engagement\EngagementServiceProvider" --tag="config"
```

## Schedule console commands

Add to your `routes/console.php` or `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('engagement:send-due-reminders')->everyMinute();
Schedule::command('engagement:match-subscriptions')->hourly();
```

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `ENGAGEMENT_TABLE_PREFIX` | `engagement_` | Prefix for all engagement tables |
| `ENGAGEMENT_JSON_COLUMN_TYPE` | `jsonb` | JSON column type |
| `ENGAGEMENT_DEFAULT_FOLLOW_NOTIFICATION_LEVEL` | `all` | Default notification level for follows |
| `ENGAGEMENT_DEFAULT_RESPONSE_VISIBILITY` | `public` | Default visibility for responses |
| `ENGAGEMENT_REMINDER_BATCH_SIZE` | `100` | Reminders processed per batch |
| `ENGAGEMENT_SUBSCRIPTION_MATCHING_BATCH_SIZE` | `100` | Subscriptions matched per batch |

## Adding traits to models

```php
use AIArmada\Engagement\Traits\CanFollow;
use AIArmada\Engagement\Traits\HasFollowers;

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

---
title: Configuration
---

## Configuration file

The `config/engagement.php` file controls all Engagement package behavior.

### Database

```php
'database' => [
    'table_prefix' => env('ENGAGEMENT_TABLE_PREFIX', 'engagement_'),
    'json_column_type' => env('ENGAGEMENT_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'follows' => env('ENGAGEMENT_TABLE_FOLLOWS', $tablePrefix . 'follows'),
        'bookmarks' => env('ENGAGEMENT_TABLE_BOOKMARKS', $tablePrefix . 'bookmarks'),
        'bookmark_collections' => env('ENGAGEMENT_TABLE_BOOKMARK_COLLECTIONS', $tablePrefix . 'bookmark_collections'),
        'bookmark_collection_items' => env('ENGAGEMENT_TABLE_BOOKMARK_COLLECTION_ITEMS', $tablePrefix . 'bookmark_collection_items'),
        'responses' => env('ENGAGEMENT_TABLE_RESPONSES', $tablePrefix . 'responses'),
        'reactions' => env('ENGAGEMENT_TABLE_REACTIONS', $tablePrefix . 'reactions'),
        'subscriptions' => env('ENGAGEMENT_TABLE_SUBSCRIPTIONS', $tablePrefix . 'subscriptions'),
        'reminders' => env('ENGAGEMENT_TABLE_REMINDERS', $tablePrefix . 'reminders'),
        'shares' => env('ENGAGEMENT_TABLE_SHARES', $tablePrefix . 'shares'),
        'engagement_counters' => env('ENGAGEMENT_TABLE_COUNTERS', $tablePrefix . 'engagement_counters'),
    ],
]
```

All table names are individually configurable via environment variables.

### Defaults

```php
'defaults' => [
    'follow_notification_level' => env('ENGAGEMENT_DEFAULT_FOLLOW_NOTIFICATION_LEVEL', 'all'),
    'response_visibility' => env('ENGAGEMENT_DEFAULT_RESPONSE_VISIBILITY', 'public'),
]
```

- `follow_notification_level`: Controls default notification behavior for new follows (`all`, `highlights`, `none`).
- `response_visibility`: Controls default visibility of responses (`public`, `private`, `connections_only`).

### Owner scoping

```php
'owner' => [
    'enabled' => env('ENGAGEMENT_OWNER_ENABLED', true),
    'include_global' => env('ENGAGEMENT_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('ENGAGEMENT_OWNER_AUTO_ASSIGN', true),
]
```

All persisted engagement records use the `commerce-support` owner boundary. Create,
query, and mutate them inside an `OwnerContext`; cross-tenant reads and writes are
blocked by default.

### Reminder

```php
'reminder' => [
    'batch_size' => (int) env('ENGAGEMENT_REMINDER_BATCH_SIZE', 100),
    'default_channels' => ['mail', 'database'],
]
```

Controls how many reminders are processed per scheduled run and which notification channels to use by default. Reminders use Laravel Notifications for delivery.

### Notifications

```php
'notifications' => [
    'reminder' => EngagementReminderNotification::class,
]
```

Override the notification class used for reminder delivery. Your custom class must extend the base notification.

### Model class overrides

```php
'models' => [
    'follow' => Follow::class,
    'bookmark' => Bookmark::class,
    'response' => Response::class,
    'reaction' => Reaction::class,
    'subscription' => Subscription::class,
    'reminder' => Reminder::class,
    'share' => Share::class,
]
```

These service-created models are configurable, allowing you to extend and replace them with your own subclasses.

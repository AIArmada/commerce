---
title: Installation
---

## Install

```bash
composer require aiarmada/events
```

## Publish and run migrations

```bash
php artisan vendor:publish --provider="AIArmada\Events\EventsServiceProvider" --tag="migrations"
php artisan migrate
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\Events\EventsServiceProvider" --tag="config"
```

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `EVENTS_TABLE_PREFIX` | (empty) | Prefix for all events tables |
| `EVENTS_JSON_COLUMN_TYPE` | `jsonb` | JSON column type (`jsonb` or `json`) |
| `EVENTS_OWNER_ENABLED` | `true` | Enable owner/multi-tenancy scoping |
| `EVENTS_OWNER_INCLUDE_GLOBAL` | `false` | Include global records in owner-scoped queries |
| `EVENTS_OWNER_AUTO_ASSIGN` | `true` | Auto-assign owner on creation |
| `EVENTS_TIMEZONE` | `APP_TIMEZONE` | Default timezone |
| `EVENTS_REGISTRATION_PREFIX` | `REG` | Prefix for auto-generated registration numbers |
| `EVENTS_TABLE_EVENTS` | `{prefix}events` | Custom events table name |
| `EVENTS_TABLE_OCCURRENCES` | `{prefix}event_occurrences` | Custom occurrences table name |
| `EVENTS_TABLE_SESSIONS` | `{prefix}event_sessions` | Custom sessions table name |
| `EVENTS_TABLE_PARTICIPANTS` | `{prefix}event_registration_participants` | Custom participants table name |

## Verify installation

```php
use AIArmada\Events\Models\Event;

$event = Event::create([
    'title' => 'Test Event',
    'status' => 'draft',
    'visibility' => 'public',
]);
```

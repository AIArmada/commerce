---
title: Installation
---

## Install

```bash
composer require aiarmada/events
```

## Publish and run migrations

```bash
php artisan vendor:publish --provider="AIArmada\Events\EventsServiceProvider" --tag="events-migrations"
php artisan migrate
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\Events\EventsServiceProvider" --tag="events-config"
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
| `EVENTS_TABLE_REGISTRATION_PARTICIPANTS` | `{prefix}event_registration_participants` | Custom participants table name |

## Seed the place-facility catalog (optional)

```php
use AIArmada\Events\Actions\SeedFacilityTypesAction;

app(SeedFacilityTypesAction::class)->execute();
```

The seeder is idempotent and never overwrites customized rows. See
[Venue Facilities](06-venue-facilities.md). Nothing seeds automatically on
boot.

> **warning**
>
> The `venue_facilities` migration was edited in place to make `venue_id`
> nullable (standalone template support) and to use `foreignUuid` columns.
> Fresh schemas use it on `migrate`. Existing installs must explicitly apply
> the nullable `venue_id` change out of band; no upgrade migration or
> backfill is provided and there is no legacy path.

## Verify installation

```php
use AIArmada\Events\Models\Event;

$event = Event::create([
    'title' => 'Test Event',
    'status' => 'draft',
    'visibility' => 'public',
]);
```

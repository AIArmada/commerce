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
| `EVENTS_TIMEZONE` | `APP_TIMEZONE` | Default display/scheduling timezone |
| `EVENTS_DEFAULT_CURRENCY` | `TICKETING_DEFAULT_CURRENCY` or `MYR` | Currency used when issuing passes without an explicit registration currency |
| `EVENTS_REGISTRATION_PREFIX` | `REG` | Prefix for auto-generated registration numbers |
| `EVENTS_TABLE_EVENTS` | `{prefix}events` | Custom events table name |
| `EVENTS_TABLE_OCCURRENCES` | `{prefix}event_occurrences` | Custom occurrences table name |
| `EVENTS_TABLE_SESSIONS` | `{prefix}event_sessions` | Custom sessions table name |
| `EVENTS_TABLE_REGISTRATIONS` | `{prefix}event_registrations` | Custom registrations table name |
| `EVENTS_TABLE_REGISTRATION_PARTICIPANTS` | `{prefix}event_registration_participants` | Custom participants table name |

Every table is individually configurable; see [Configuration](03-configuration.md#database).

## Seed the place-facility catalog (optional)

```php
use AIArmada\Events\Features\Venues\Actions\SeedFacilityTypesAction;

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

> **warning**
>
> The owning create migrations now define the nullable
> `issue_passes_for_free` inheritance columns, the registration composite
> lookup indexes, and the search-document identity unique index. Fresh
> installs receive them on `migrate`. Existing installs must apply the same
> column/index changes out of band; this package does not ship standalone
> upgrade migrations or data backfills. The search identity index requires
> duplicate `(searchable_type, searchable_id, document_type)` rows to be
> resolved before it is added. PostgreSQL installations can retain the
> existing `*_facets_gin_default_idx` and remove the redundant
> `*_facets_gin_idx` after checking host-specific query plans.

## Verify installation

Resolve the configured event model through the platform resolver — never
import a concrete model class in application code when the app may override
`events.models.event`:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Platform\ModelResolver;

$event = OwnerContext::withOwner($owner, function (): object {
    $eventClass = ModelResolver::eventClass();

    return $eventClass::query()->create([
        'title' => 'Test Event',
        'status' => 'draft',
        'visibility' => 'public',
    ]);
});
```

`status` and `visibility` are `NOT NULL` with no database default, so every
create must set both explicitly.

> **warning**
>
> This release is a breaking Modular Dune reorganization. Legacy class names
> directly under `AIArmada\Events` (the old `Models`, `Enums`, `States`,
> `Actions`, `Services`, `Support`, `Resolvers`, `Listeners`, `Observers`,
> `Policies`, `Jobs`, `Steps`, `Checkout`, `Integrations`, and `Facades`
> directories) plus the `Events` facade no longer exist. Update imports to
> `Features\...`, `Contracts\...`, `Data\...`, `Drivers\...`, or
> `Platform\...` before upgrading. See
> [Troubleshooting](99-troubleshooting.md#legacy-namespaces-were-removed) for
> the full mapping.

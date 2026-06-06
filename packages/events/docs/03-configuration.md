---
title: Configuration
---

# Configuration

All package options live in `config/events.php`.

## Database

```php
'database' => [
    'table_prefix' => 'commerce_event_',
    'json_column_type' => env('EVENTS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    'tables' => [
        'series' => 'commerce_event_series',
        'events' => 'commerce_events',
        'speakers' => 'commerce_event_speakers',
        'venues' => 'commerce_event_venues',
        'occurrences' => 'commerce_event_occurrences',
        'registrations' => 'commerce_event_registrations',
    ],
],
```

### `database.table_prefix`

The fallback prefix used when a table name is not overridden in `database.tables`.

### `database.json_column_type`

Controls the JSON column type used by package migrations.

### `database.tables`

Override individual table names for:

- `series`
- `events`
- `speakers`
- `venues`
- `occurrences`
- `registrations`

The defaults are intentionally package-specific to avoid collisions with a host application's own `events` table. Existing installs that already migrated older package defaults can pin those names:

```php
'tables' => [
    'series' => 'event_series',
    'events' => 'events',
    'speakers' => 'event_speakers',
    'venues' => 'event_venues',
    'occurrences' => 'event_occurrences',
    'registrations' => 'event_registrations',
],
```

Equivalent environment overrides are available: `EVENTS_TABLE_SERIES`, `EVENTS_TABLE_EVENTS`, `EVENTS_TABLE_SPEAKERS`, `EVENTS_TABLE_VENUES`, `EVENTS_TABLE_OCCURRENCES`, and `EVENTS_TABLE_REGISTRATIONS`.

## Models

```php
'models' => [
    'event' => \AIArmada\Events\Models\Event::class,
    'organizer' => null,
    'speaker' => null,
    'venue' => \AIArmada\Events\Models\Venue::class,
],
```

### `models.event`

The Eloquent model class returned by `Occurrence::event()` and `EventSeries::events()`.

Set this to a host application's canonical event model when the package should manage occurrences and registrations for a richer public event record.

### `models.venue`

The Eloquent model class returned by `Occurrence::venue()`.

Set this to a host application's venue/location model when the package should reference host venue records.

### `models.organizer`

Optional documentation seam for the host application's organizer model. Organizer links are stored as morph columns on events, so the package does not need this value to resolve the relationship.

### `models.speaker`

Optional documentation seam for the host application's speaker model. Speaker links are stored as morph columns on `EventSpeaker`, so display-only speakers and app-owned speaker records can coexist.

## Features

```php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],
],
```

### `features.owner`

Owner scoping controls for all event-domain models.

- `enabled`: apply owner scoping to series, events, venues, occurrences, and registrations
- `include_global`: allow readable owner-scoped queries to include global rows
- `auto_assign_on_create`: stamp the current owner on new rows when owner columns are omitted

## Codes

```php
'codes' => [
    'registration_prefix' => 'REG',
    'registration_length' => 10,
],
```

### `codes.registration_prefix`

Prefix used when generating registration codes.

### `codes.registration_length`

Total registration-code length, including the prefix.

## Defaults

```php
'defaults' => [
    'occurrence_participation_mode' => 'registration_required',
    'event_moderation_status' => 'approved',
    'event_visibility' => 'public',
],
```

### `defaults.occurrence_participation_mode`

Default participation mode for newly created occurrences when no explicit mode is provided.

Supported values:

- `none`
- `registration_required`
- `walk_in_only`
- `hybrid`

### `defaults.event_moderation_status`

Default moderation state for package-owned events created without an explicit moderation value.

Supported values:

- `pending`
- `approved`
- `rejected`

### `defaults.event_visibility`

Default public visibility for package-owned events created without an explicit visibility value.

Supported values:

- `public`
- `unlisted`
- `private`

## Media

```php
'media' => [
    'collections' => [
        'cover' => 'cover',
        'poster' => 'poster',
        'gallery' => 'gallery',
    ],
],
```

The core package stores package-neutral media references in the event `media_references` JSON column and exposes collection names through `Event::mediaCollections()`. Applications that use Spatie Media Library or another asset system can bind those collection names to their own adapters without adding a hard dependency to the core package.

## Taxonomy

```php
'taxonomy' => [
    'groups' => [
        'category',
        'topic',
        'audience',
        'language',
    ],
],
```

The core package stores package-neutral taxonomy payloads in the event `taxonomy` JSON column. Applications can map those groups to Spatie Tags, custom taxonomies, or search-engine facets.

## Search

```php
'search' => [
    'payload_resolver' => null,
],
```

Set `search.payload_resolver` to a class implementing `AIArmada\Events\Contracts\EventSearchPayloadResolver` when an application needs a custom Scout, Meilisearch, Typesense, or Algolia payload. If it is `null`, the package uses `DefaultEventSearchPayloadResolver`.

## Timezone

```php
'timezone' => [
    'default' => 'UTC',
    'display_timezone_resolver' => null,
],
```

The package stores timestamps in UTC and keeps the source timezone label on event / occurrence records. Set `timezone.display_timezone_resolver` to a class implementing `AIArmada\Events\Contracts\EventDisplayTimezoneResolver` when viewer-specific display behavior is needed.

## Lifecycle

```php
'lifecycle' => [
    'occurrence' => [
        'registration_accepting_statuses' => ['scheduled', 'live'],
        'check_in_accepting_statuses' => ['scheduled', 'live'],
        'walk_in_accepting_statuses' => ['scheduled', 'live'],
    ],
    'registration' => [
        'check_in_allowed_statuses' => ['confirmed'],
        'capacity_blocking_statuses' => ['pending', 'confirmed', 'checked_in', 'no_show'],
        'terminal_statuses' => ['checked_in', 'cancelled', 'refunded', 'no_show'],
    ],
],
```

### `lifecycle.occurrence.registration_accepting_statuses`

Occurrence statuses that can accept new registrations when the registration window is also open.

### `lifecycle.occurrence.check_in_accepting_statuses`

Occurrence statuses that can accept check-in when the check-in window is also open.

### `lifecycle.occurrence.walk_in_accepting_statuses`

Occurrence statuses that can accept walk-in attendance when the check-in window is also open.

### `lifecycle.registration.check_in_allowed_statuses`

Registration statuses that can transition to checked-in.

### `lifecycle.registration.capacity_blocking_statuses`

Registration statuses counted against occurrence capacity. `waitlisted` does not block capacity by default.

### `lifecycle.registration.terminal_statuses`

Registration statuses treated as complete for ended-event order completion checks.

## Integrations

The package resolves related models from config so events and registrations can link back to the commerce layer when the first-party packages are installed:

```php
'integrations' => [
    'product_model' => class_exists(\AIArmada\Products\Models\Product::class)
        ? \AIArmada\Products\Models\Product::class
        : null,
    'variant_model' => class_exists(\AIArmada\Products\Models\Variant::class)
        ? \AIArmada\Products\Models\Variant::class
        : null,
    'customer_model' => class_exists(\AIArmada\Customers\Models\Customer::class)
        ? \AIArmada\Customers\Models\Customer::class
        : null,
    'order_model' => class_exists(\AIArmada\Orders\Models\Order::class)
        ? \AIArmada\Orders\Models\Order::class
        : null,
    'order_item_model' => class_exists(\AIArmada\Orders\Models\OrderItem::class)
        ? \AIArmada\Orders\Models\OrderItem::class
        : null,
    'order_item_fulfillment_resolver' => null,
],
```

These integrations are read by occurrence and registration relationships plus the order-fulfillment flows.

When a related package is missing, its config value resolves to `null`. The core package still boots and core event registrations continue to work. Calling a commerce-specific relationship such as `Registration::order()` without the matching package installed throws a clear integration error.

Order fulfillment features are auto-registered only when the AIArmada customers and orders package classes are available.

### `integrations.order_item_fulfillment_resolver`

Set this to a class implementing `AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver` when order items should create event registrations.

If it is `null`, the package binds a no-op resolver and order fulfillment returns an empty registration collection.

## Record-level occurrence settings

Occurrence availability is controlled by model fields plus lifecycle policy config:

- `capacity`
- `registration_opens_at`
- `registration_closes_at`
- `check_in_opens_at`
- `check_in_closes_at`

`RegistrationService` enforces those values during create and check-in operations.

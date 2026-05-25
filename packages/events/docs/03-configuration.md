---
title: Configuration
---

# Configuration

All package options live in `config/events.php`.

## Database

```php
'database' => [
    'table_prefix' => 'event_',
    'json_column_type' => env('EVENTS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    'tables' => [
        'series' => 'event_series',
        'events' => 'events',
        'venues' => 'event_venues',
        'occurrences' => 'event_occurrences',
        'registrations' => 'event_registrations',
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
- `venues`
- `occurrences`
- `registrations`

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

## Integrations

The package resolves related models from config so registrations can link back to the commerce layer:

```php
'integrations' => [
    'product_model' => \AIArmada\Products\Models\Product::class,
    'variant_model' => \AIArmada\Products\Models\Variant::class,
    'customer_model' => \AIArmada\Customers\Models\Customer::class,
    'order_model' => \AIArmada\Orders\Models\Order::class,
    'order_item_model' => \AIArmada\Orders\Models\OrderItem::class,
],
```

These integrations are read by occurrence and registration relationships plus the order-fulfillment flows.

## Record-level occurrence settings

Occurrence availability is controlled by model fields instead of package-wide config:

- `capacity`
- `registration_opens_at`
- `registration_closes_at`
- `check_in_opens_at`
- `check_in_closes_at`

`RegistrationService` enforces those values during create and check-in operations.
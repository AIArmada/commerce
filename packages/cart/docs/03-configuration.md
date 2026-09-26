---
title: Configuration
---

# Configuration

The cart config is intentionally small. If a key is present, it is actively used by the package.

## Database

```php
'database' => [
    'json_column_type' => env('CART_JSON_COLUMN_TYPE', 'jsonb'),
    'table' => env('CART_DB_TABLE', 'carts'),
    'conditions_table' => env('CART_CONDITIONS_TABLE', 'conditions'),
    'tables' => [
        'snapshots' => env('CART_SNAPSHOTS_TABLE', 'cart_snapshots'),
        'snapshot_items' => env('CART_SNAPSHOT_ITEMS_TABLE', 'cart_snapshot_items'),
        'snapshot_conditions' => env('CART_SNAPSHOT_CONDITIONS_TABLE', 'cart_snapshot_conditions'),
    ],
    'ttl' => env('CART_DB_TTL', 60 * 60 * 24 * 30),
    'lock_for_update' => env('CART_DB_LOCK_FOR_UPDATE', false),
],
```

`json_column_type` is read by every cart migration, including the carts and
conditions tables. Set it to `json` when the driver does not support `jsonb`.
`ttl` is expressed in seconds; pass `null` to disable expiry.

## Defaults

```php
'money' => [
    'default_currency' => env('CART_DEFAULT_CURRENCY', 'MYR'),
    'rounding_mode' => env('CART_ROUNDING_MODE', 'half_up'),
],
```

Cart prices, condition values, and totals are integer minor units. Money objects
use the configured ISO 4217 currency with conversion disabled; decimal strings
are normalized before formatting. Use `CartMoney::formatMinor()` for
user-facing values.

## Behavior

```php
'empty_cart_behavior' => env('CART_EMPTY_BEHAVIOR', 'destroy'),

'migration' => [
    'auto_migrate_on_login' => env('CART_AUTO_MIGRATE', true),
    'merge_strategy' => env('CART_MERGE_STRATEGY', 'add_quantities'),
],

'events' => env('CART_EVENTS_ENABLED', true),

'dynamic_rules_factory' => null,

'conditions' => [
    'apply_global' => env('CART_APPLY_GLOBAL_CONDITIONS', true),
],

'snapshots' => [
    'analytics' => [
        'high_value_threshold_minor' => env('CART_HIGH_VALUE_THRESHOLD_MINOR', 10000),
    ],
    'abandonment_tracking' => env('CART_ABANDONMENT_TRACKING', true),
    'abandonment_detection_minutes' => env('CART_ABANDONMENT_DETECTION_MINUTES', 30),
    'synchronization' => [
        'queue_sync' => env('CART_QUEUE_SNAPSHOT_SYNC', true),
        'queue_connection' => env('CART_SNAPSHOT_QUEUE_CONNECTION'),
        'queue_name' => env('CART_SNAPSHOT_QUEUE_NAME', 'cart-sync'),
    ],
],
```

- `dynamic_rules_factory` defaults to `null`, which resolves to
  `AIArmada\Cart\Services\BuiltInRulesFactory`. Set it to a class name to replace
  the factory.
- `conditions.apply_global` gates the `ApplyGlobalConditions` listener that applies
  globally applicable conditions on cart create and item changes. See
  [Conditions](05-conditions.md).
- `snapshots.analytics.high_value_threshold_minor` is an integer minor-unit
  amount. A `HighValueCartDetected` event fires when a sync pushes the cart total
  from below the threshold to at or above it. Set it to `0` to disable.
- `snapshots.abandonment_tracking` gates the `cart:clear-abandoned --mark-only`
  snapshot marking path.
- `snapshots.synchronization.queue_connection` is `null` by default, so snapshot
  sync uses the application's default queue connection.

## Owner scoping

```php
'owner' => [
    'enabled' => env('CART_OWNER_ENABLED', false),
    'include_global' => env('CART_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('CART_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

When owner mode is enabled, cart reads and writes require a resolved owner context or an explicit global context via `OwnerContext::withOwner(null, ...)`.

Installing filament-cart does not enable or rewrite cart.owner.*. Enable
cart.owner.enabled explicitly for core cart and condition storage. Filament Cart
uses the same core owner boundary and has no independent owner switch.

CartFactory is scoped to the application request/job lifecycle so its storage
cannot retain an owner from a previous Octane request.

## Limits

```php
'limits' => [
    'max_items' => env('CART_MAX_ITEMS', 1000),
    'max_item_quantity' => env('CART_MAX_QUANTITY', 10000),
    'max_data_size_bytes' => env('CART_MAX_DATA_BYTES', 1048576),
    'max_string_length' => env('CART_MAX_STRING_LENGTH', 255),
],
```

## Performance

```php
'performance' => [
    'lazy_pipeline' => env('CART_LAZY_PIPELINE_ENABLED', true),
],
```

## Removed local intelligence config

Cart no longer defines table/config keys for local recovery, metrics, popup interventions, or alerting. Use `aiarmada/signals` for optional analytics and alerts.

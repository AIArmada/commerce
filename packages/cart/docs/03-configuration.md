---
title: Configuration
---

# Configuration

The cart config is intentionally small. If a key is present, it is actively used by the package.

## Database

```php
'database' => [
    'table' => env('CART_DB_TABLE', 'carts'),
    'table_prefix' => env('CART_DB_TABLE_PREFIX', 'cart_'),
    'conditions_table' => env('CART_CONDITIONS_TABLE', 'conditions'),
    'json_column_type' => env('CART_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    'ttl' => env('CART_DB_TTL', 60 * 60 * 24 * 30),
    'lock_for_update' => env('CART_DB_LOCK_FOR_UPDATE', false),
],
```

## Defaults

```php
'models' => [
    'cart' => AIArmada\Cart\Models\CartModel::class,
],

'money' => [
    'default_currency' => env('CART_DEFAULT_CURRENCY', 'MYR'),
    'rounding_mode' => env('CART_ROUNDING_MODE', 'half_up'),
],
```

## Behavior

```php
'empty_cart_behavior' => env('CART_EMPTY_BEHAVIOR', 'destroy'),

'migration' => [
    'auto_migrate_on_login' => env('CART_AUTO_MIGRATE', true),
    'merge_strategy' => env('CART_MERGE_STRATEGY', 'add_quantities'),
],

'events' => env('CART_EVENTS_ENABLED', true),
```

## Owner scoping

```php
'owner' => [
    'enabled' => env('CART_OWNER_ENABLED', false),
    'include_global' => env('CART_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('CART_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

When owner mode is enabled, cart reads and writes require a resolved owner context or an explicit global context via `OwnerContext::withOwner(null, ...)`.

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

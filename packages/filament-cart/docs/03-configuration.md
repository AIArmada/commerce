---
title: Configuration
---

# Configuration

Configuration lives in `config/filament-cart.php`.

## Database

```php
'database' => [
    'table_prefix' => 'cart_',
    'json_column_type' => env('FILAMENT_CART_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'snapshots' => 'cart_snapshots',
        'snapshot_items' => 'cart_snapshot_items',
        'snapshot_conditions' => 'cart_snapshot_conditions',
    ],
],
```

## Navigation

```php
'navigation' => [
        'group' => 'E-Commerce'
    ],

'resources' => [
    'navigation_sort' => [
        'carts' => 30,
    ],
],
```

## Tables

```php
'polling_interval' => '30s',
```

## Features

```php
'features' => [
    'dashboard' => true,
    'monitoring' => true,
    'global_conditions' => true,
    'abandonment_tracking' => true,
],
```

## Integrations

```php
'dynamic_rules_factory' => AIArmada\Cart\Services\BuiltInRulesFactory::class,
```

Override this class when custom dynamic condition rule factories are needed.

## Owner scoping

```php
'owner' => [
    'enabled' => env('FILAMENT_CART_OWNER_ENABLED'),
    'include_global' => env('FILAMENT_CART_OWNER_INCLUDE_GLOBAL'),
    'auto_assign_on_create' => env('FILAMENT_CART_OWNER_AUTO_ASSIGN_ON_CREATE'),
],
```

Filament Cart does not write to cart.owner.*. If a Filament owner setting is
unset, it falls back to the corresponding core cart setting; setting it to
false is an explicit override. The fallback applies to snapshots and their
children. Stored conditions use the core cart owner boundary, so both
cart.owner.enabled and filament-cart.owner.enabled must be enabled when the
condition resource should be owner-scoped. Owner-protected reads and writes
require a resolved owner or explicit global context.

The auto-assign setting follows the same fallback and uses
cart.owner.auto_assign_on_create when the Filament setting is unset.

Snapshot migrations use filament-cart.database.json_column_type, falling back
to cart.database.json_column_type when the Filament key is unset. PostgreSQL
GIN indexes are created only for the resolved jsonb type.

## Widgets

```php
'widgets' => [
    'stats_overview' => true,
    'abandoned_carts' => true,
],
```

## Operational thresholds

```php
'analytics' => [
    'high_value_threshold_minor' => 10000,
],
```

This threshold controls when `HighValueCartDetected` is emitted. The package does not persist local analytics tables.

## Monitoring

```php
'monitoring' => [
    'abandonment_detection_minutes' => 30,
],
```

Use `cart:mark-abandoned` to mark abandoned snapshots. The command uses this value when `--minutes` is not provided. Alert evaluation and notification dispatch belong to Signals.

## Synchronization

```php
'synchronization' => [
    'queue_sync' => false,
    'queue_connection' => 'default',
    'queue_name' => 'cart-sync',
],
```

## Notifications

```php
'notifications' => [
    'abandoned_cart' => [
        'enabled' => env('FILAMENT_CART_ABANDONED_NOTIFICATION_ENABLED', true),
        'from_address' => env('FILAMENT_CART_ABANDONED_FROM', 'info@unfairadvantage.my'),
        'from_name' => env('FILAMENT_CART_ABANDONED_FROM_NAME'),
        'brand_name' => env('FILAMENT_CART_ABANDONED_BRAND_NAME', 'Unfair Advantage'),
    ],
],
```

This notification is dispatched when an abandoned cart is marked on the `CartAbandoned` event.

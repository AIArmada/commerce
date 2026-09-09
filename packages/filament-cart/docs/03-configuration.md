---
title: Configuration
---

# Configuration

UI configuration lives in `config/filament-cart.php`. Cart storage, snapshots,
owner scoping, money, synchronization, and abandonment detection live in
`config/cart.php`.

## Database and snapshots

The Filament adapter does not define database tables or migrations. Configure
the core package:

```php
// config/cart.php
'database' => [
    'json_column_type' => env('CART_JSON_COLUMN_TYPE', 'jsonb'),
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
],
```

## Integrations

```php
// config/cart.php
'dynamic_rules_factory' => AIArmada\Cart\Services\BuiltInRulesFactory::class,
```

Override this class when custom dynamic condition rule factories are needed.

## Owner scoping

Enable and configure owner scoping only in `config/cart.php`. Filament
resources and widgets use that same core owner boundary; the adapter does not
mutate or shadow owner configuration. Owner-protected reads and writes require
a resolved owner or explicit global context.

## Widgets

```php
'widgets' => [
    'stats_overview' => true,
    'abandoned_carts' => true,
],
```

## Operational thresholds

```php
// config/cart.php
'snapshots' => [
    'analytics' => [
        'high_value_threshold_minor' => 10000,
    ],
],
```

This threshold controls when `HighValueCartDetected` is emitted. The package does not persist local analytics tables.

## Monitoring

```php
// config/cart.php
'snapshots' => [
    'abandonment_detection_minutes' => 30,
],
```

Use `cart:clear-abandoned --mark-only` to mark abandoned snapshots. The core
command uses this value when `--minutes` is not provided. Alert evaluation and
notification dispatch belong to Signals.

## Synchronization

```php
// config/cart.php
'snapshots' => [
    'synchronization' => [
        'queue_sync' => true,
        'queue_connection' => null, // Uses queue.default when omitted
        'queue_name' => 'cart-sync',
    ],
],
```

## Notifications

```php
'notifications' => [
    'abandoned_cart' => [
        'enabled' => env('FILAMENT_CART_ABANDONED_NOTIFICATION_ENABLED', true),
        'from_address' => env('FILAMENT_CART_ABANDONED_FROM', 'info@example.com'),
        'from_name' => env('FILAMENT_CART_ABANDONED_FROM_NAME'),
        'brand_name' => env('FILAMENT_CART_ABANDONED_BRAND_NAME', config('app.name')),
    ],
],
```

This notification is dispatched when an abandoned cart is marked on the `CartAbandoned` event.

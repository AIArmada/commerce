---
title: Configuration
---

# Configuration

Configuration lives in `config/filament-cart.php`.

## Database

```php
'database' => [
    'table_prefix' => 'cart_',
    'json_column_type' => env('FILAMENT_CART_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    'tables' => [
        'snapshots' => 'cart_snapshots',
        'snapshot_items' => 'cart_snapshot_items',
        'snapshot_conditions' => 'cart_snapshot_conditions',
    ],
],
```

## Navigation

```php
'navigation_group' => 'E-Commerce',

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
    'enabled' => env('FILAMENT_CART_OWNER_ENABLED', false),
    'include_global' => env('FILAMENT_CART_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('FILAMENT_CART_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

Owner mode is synchronized with `cart.owner`. Reads and writes require a resolved owner or explicit global context.

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

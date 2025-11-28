# Configuration Guide

Complete configuration reference for the stock package.

## Publishing Configuration

```bash
php artisan vendor:publish --tag=stock-config
```

This creates `config/stock.php` in your application.

## Configuration Options

### Table Name

```php
'table_name' => env('STOCK_TABLE_NAME', 'stock_transactions'),
```

The database table for stock transactions. Change if you have naming conflicts.

### Low Stock Threshold

```php
'low_stock_threshold' => env('STOCK_LOW_THRESHOLD', 10),
```

Default threshold for `isLowStock()` checks. Can be overridden per-call:

```php
$product->isLowStock();    // Uses config threshold (10)
$product->isLowStock(25);  // Uses custom threshold (25)
```

## Cart Integration

```php
'cart' => [
    'enabled' => env('STOCK_CART_INTEGRATION', true),
    'reservation_ttl' => env('STOCK_RESERVATION_TTL', 30),
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `enabled` | `true` | Enable cart package integration |
| `reservation_ttl` | `30` | Default reservation expiry in minutes |

## Payment Integration

```php
'payment' => [
    'auto_deduct' => env('STOCK_AUTO_DEDUCT', true),
    'events' => [
        // 'App\Events\OrderPaid',
    ],
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `auto_deduct` | `true` | Auto-deduct stock on payment success |
| `events` | `[]` | Custom payment event classes to listen for |

The package automatically listens to:
- `AIArmada\CashierChip\Events\PaymentSucceeded`
- `AIArmada\Cashier\Events\PaymentSucceeded`

Add custom events:

```php
'events' => [
    App\Events\OrderPaid::class,
    App\Events\PaymentCompleted::class,
],
```

## Event Dispatching

```php
'events' => [
    'low_stock' => env('STOCK_EVENT_LOW_STOCK', true),
    'out_of_stock' => env('STOCK_EVENT_OUT_OF_STOCK', true),
    'reserved' => env('STOCK_EVENT_RESERVED', true),
    'released' => env('STOCK_EVENT_RELEASED', true),
    'deducted' => env('STOCK_EVENT_DEDUCTED', true),
],
```

Disable specific events to reduce overhead:

```env
STOCK_EVENT_RESERVED=false
STOCK_EVENT_RELEASED=false
```

## Cleanup Settings

```php
'cleanup' => [
    'keep_expired_for_minutes' => env('STOCK_KEEP_EXPIRED', 0),
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `keep_expired_for_minutes` | `0` | Grace period before deleting expired reservations |

Set to a positive value to keep expired reservations for debugging:

```env
STOCK_KEEP_EXPIRED=60
```

This keeps expired reservations for 60 minutes before cleanup.

## Environment Variables Reference

```env
# Table configuration
STOCK_TABLE_NAME=stock_transactions

# Thresholds
STOCK_LOW_THRESHOLD=10

# Cart integration
STOCK_CART_INTEGRATION=true
STOCK_RESERVATION_TTL=30

# Payment integration
STOCK_AUTO_DEDUCT=true

# Event dispatching
STOCK_EVENT_LOW_STOCK=true
STOCK_EVENT_OUT_OF_STOCK=true
STOCK_EVENT_RESERVED=true
STOCK_EVENT_RELEASED=true
STOCK_EVENT_DEDUCTED=true

# Cleanup
STOCK_KEEP_EXPIRED=0
```

## Complete Configuration Example

```php
<?php

return [
    'table_name' => env('STOCK_TABLE_NAME', 'stock_transactions'),
    'low_stock_threshold' => env('STOCK_LOW_THRESHOLD', 10),

    'cart' => [
        'enabled' => env('STOCK_CART_INTEGRATION', true),
        'reservation_ttl' => env('STOCK_RESERVATION_TTL', 30),
    ],

    'payment' => [
        'auto_deduct' => env('STOCK_AUTO_DEDUCT', true),
        'events' => [],
    ],

    'events' => [
        'low_stock' => env('STOCK_EVENT_LOW_STOCK', true),
        'out_of_stock' => env('STOCK_EVENT_OUT_OF_STOCK', true),
        'reserved' => env('STOCK_EVENT_RESERVED', true),
        'released' => env('STOCK_EVENT_RELEASED', true),
        'deducted' => env('STOCK_EVENT_DEDUCTED', true),
    ],

    'cleanup' => [
        'keep_expired_for_minutes' => env('STOCK_KEEP_EXPIRED', 0),
    ],
];
```

---
title: Configuration
---

# Configuration

The package exposes a single `events.php` config file.

## Database tables

```php
'database' => [
    'table_prefix' => 'event_',
    'tables' => [
        'series' => 'event_series',
        'events' => 'events',
        'venues' => 'event_venues',
        'occurrences' => 'event_occurrences',
        'registrations' => 'event_registrations',
    ],
],
```

## Owner scoping

```php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],
],
```

## Registration codes

```php
'codes' => [
    'registration_prefix' => 'REG',
    'registration_length' => 10,
],
```

## Commerce integrations

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
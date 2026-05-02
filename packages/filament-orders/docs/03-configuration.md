---
title: Configuration
---

# Configuration

All configuration options are defined in `config/filament-orders.php`.

## Navigation

Configure navigation group and sort order:

```php
'navigation' => [
    'group' => 'Sales',
    'sort' => 1,
],
```

## Navigation

Configure navigation group and sort order:

```php
'navigation' => [
    'group' => 'Sales',
    'sort' => 1,
],
```

## Payment Gateways

Define available payment gateways for manual payment confirmation:

```php
'payment_gateways' => [
    'stripe' => 'Stripe',
    'chip' => 'CHIP',
    'manual' => 'Manual',
],
```

These appear in the "Confirm Payment" action dropdown.

## Features

Toggle optional features:

```php
'features' => [
    // Show invoice download button
    'enable_invoice_download' => true,
],
```

## Full Configuration Example

```php
<?php

declare(strict_types=1);

return [
    /* Navigation */
    'navigation' => [
        'group' => 'Sales',
        'sort' => 1,
    ],

    /* Tables */
    'tables' => [
        'poll_interval' => '30s',
        'date_format' => 'd M Y, H:i',
    ],

    /* Payment Gateways */
    'payment_gateways' => [
        'stripe' => 'Stripe',
        'chip' => 'CHIP',
        'manual' => 'Manual',
    ],

    /* Features */
    'features' => [
        'enable_invoice_download' => true,
    ],
];
```

## Core Package Configuration

Remember to also configure the core orders package:

```php
// config/orders.php
return [
    'database' => [
        'tables' => [...],
        'json_column_type' => 'json',
    ],
    
    'currency' => [
        'default' => 'MYR',
    ],
    
    'owner' => [
        'enabled' => true,
        'include_global' => false,
    ],
    
    // ... see orders package documentation
];
```

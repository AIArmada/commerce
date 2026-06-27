---
title: Configuration
---

# Configuration

`filament-customers` publishes `config/filament-customers.php` for navigation and optional page toggles.

## Package Config

```php
return [
    'navigation' => [
        'group' => 'CRM',
    ],
    'features' => [
        'merge_customers' => true,
        'segment_rebuild' => false,
        'address_validation' => false,
    ],
    'resources' => [
        'navigation_sort' => [
            'customers' => 1,
            'segments' => 2,
        ],
    ],
    'pages' => [
        'navigation_sort' => [
            'merge_customers' => 10,
            'segment_rebuild' => 99,
            'address_validation' => 100,
        ],
    ],
];
```

## Where configuration happens

This package is configured through these surfaces:

### Panel plugin registration

Register the plugin in each Filament panel that should expose the package resources and widgets:

```php
use AIArmada\FilamentCustomers\FilamentCustomersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCustomersPlugin::make(),
        ]);
}
```

### Core package configuration

Owner-scoping and customer-domain settings live in `config/customers.php`, not in this package.

Important keys to understand before using the Filament plugin:

- `customers.database.*`
- `customers.features.owner.*`
- `customers.features.segments.auto_assign`
- `customers.integrations.user_model`

When customer owner mode is enabled, the Filament resources inherit those owner-scoping rules.

### Resource and widget extension

Customization happens by extending or replacing the package resources/widgets in your application.

Typical extension points:

- `CustomerResource`
- `SegmentResource`
- `CustomerStatsWidget`
- `RecentCustomersWidget`

## Owner-scoping behavior

This package does not resolve tenant or owner context itself.

Instead, it expects the application to provide owner context before resource queries run. The package then applies owner-safe query scoping through shared helpers such as `OwnerUiScope`.

That means:

- resource list queries are owner-scoped,
- relationship select queries are owner-scoped,
- submitted IDs are revalidated server-side before sync or mutation,
- bulk actions authorize records individually.

## Publishing Config

Publish the package config when you need to customize navigation or optional page availability:

```bash
php artisan vendor:publish --tag=filament-customers-config
```

If you need different forms, tables, or widgets, extend the resources/widgets in your application or replace them at the panel level.

## Read next

- [Installation](02-installation.md)
- [Usage](04-usage.md)
- [Widgets](05-widgets.md)
- [Troubleshooting](99-troubleshooting.md)

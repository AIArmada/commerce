---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- `aiarmada/orders` package (installed automatically)

## Install via Composer

```bash
composer require aiarmada/filament-orders
```

This will automatically install the core `aiarmada/orders` package.

## Register the Plugin

Add the plugin to your Filament panel:

```php
// app/Providers/Filament/AdminPanelProvider.php

namespace App\Providers\Filament;

use AIArmada\FilamentOrders\FilamentOrdersPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // ... other configuration
            ->plugins([
                FilamentOrdersPlugin::make(),
            ]);
    }
}
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-orders-config
```

This publishes `config/filament-orders.php`.

## Publish Views (Optional)

To customize widget and page templates:

```bash
php artisan vendor:publish --tag=filament-orders-views
```

## Run Core Package Migrations

Ensure the core orders package migrations are run:

```bash
php artisan migrate
```

## Authorization

The package uses Laravel's authorization system. Ensure your user model has the required abilities:

```php
// Required abilities for orders
'view_any_order'
'view_order'
'create_order'
'update_order'
'delete_order'
'cancel_order'    // For cancel action
'add_note_order'  // For adding notes
```

### Using Spatie Permission

```php
// Create permissions
Permission::create(['name' => 'view_any_order']);
Permission::create(['name' => 'view_order']);
Permission::create(['name' => 'create_order']);
Permission::create(['name' => 'update_order']);
Permission::create(['name' => 'delete_order']);

// Assign to role
$role = Role::findByName('admin');
$role->givePermissionTo([
    'view_any_order',
    'view_order',
    'create_order', 
    'update_order',
    'delete_order',
]);
```

### Using Shield

The package is compatible with Filament Shield for auto-generated permissions.

## Multi-tenancy Setup

If using multi-tenancy, configure the core orders package:

```php
// config/orders.php
'owner' => [
    'enabled' => true,
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

And ensure your panel has tenant support configured.

## Next Steps

- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Customization](05-customization.md)

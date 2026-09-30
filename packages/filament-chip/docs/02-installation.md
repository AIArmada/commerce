---
title: Installation
---

# Installation

## Requirements

- PHP ^8.5
- Laravel ^13.0
- Filament ^5.0
- [aiarmada/chip](../../chip) (automatically installed as dependency)

## Install Package

```bash
composer require aiarmada/filament-chip
```

This will also install `aiarmada/chip` if not already present.

## Register Plugin

Add the plugin to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentChip\FilamentChipPlugin;
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
            ->plugin(FilamentChipPlugin::make())
            // ... other configuration
            ;
    }
}
```

## Publish Configuration

```bash
php artisan vendor:publish --tag="filament-chip-config"
```

This creates `config/filament-chip.php` with customizable options.

## Publish Migrations (if needed)

The core `chip` package handles migrations. Run them if you haven't:

```bash
php artisan migrate
```

## Configure CHIP Credentials

Ensure your `.env` has the CHIP API credentials:

```env
# CHIP Collect (Payments)
CHIP_ENVIRONMENT=sandbox
CHIP_COLLECT_BRAND_ID=your-brand-uuid
CHIP_COLLECT_API_KEY=your-collect-api-key

# CHIP Send (Payouts) - optional
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-api-secret

# Webhooks
CHIP_COLLECT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
```

## Verify Installation

Navigate to your Filament admin panel. You should see:
- "CHIP Operations" navigation group with Purchase, Payment, Client, Send Instruction, and Bank Account resources
- Analytics dashboard page
- CHIP stats widgets (if using dashboard widgets)

## Optional: Billing Portal

The billing portal is owned by `aiarmada/filament-cashier-chip`, not this package.
Install it and register its panel provider:

```bash
composer require aiarmada/filament-cashier-chip
```

```php
// bootstrap/providers.php (Laravel 13)
return [
    // ...
    AIArmada\FilamentCashierChip\CustomerPortal\BillingPanelProvider::class,
];
```

The panel path and identity come from `config/filament-cashier-chip.php`
(`billing.path`, `billing.panel_id`). Add the `Billable` trait to your billable
model:

```php
<?php

namespace App\Models;

use AIArmada\CashierChip\Billing\Billable;
use AIArmada\CashierChip\Contracts\BillableContract;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements BillableContract
{
    use Billable;

    // ...
}
```

See the [filament-cashier-chip installation guide](../../filament-cashier-chip/docs/02-installation.md)
for portal configuration (`config/filament-cashier-chip.php`).

## Multi-Panel Setup

Register the plugin on multiple panels:

```php
// AdminPanelProvider
$panel->plugin(FilamentChipPlugin::make());

// TenantPanelProvider
$panel->plugin(
    FilamentChipPlugin::make()
        ->operatorResources()
        ->developerResources(false)
);
```

The navigation group is read from `filament-chip.navigation.group`.

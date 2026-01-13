---
title: Installation
---

# Installation

## Composer

```bash
composer require aiarmada/tax
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=tax-config
```

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables:
- `tax_zones` - Geographic tax regions
- `tax_rates` - Tax percentages per zone/class
- `tax_classes` - Product categorization
- `tax_exemptions` - Customer exemptions

## Publish Settings (Optional)

If using Spatie Laravel Settings for runtime configuration:

```bash
php artisan vendor:publish --tag=tax-settings
php artisan migrate
```

## Service Provider

The package auto-registers via Laravel's package discovery. Manual registration:

```php
// config/app.php
'providers' => [
    AIArmada\Tax\TaxServiceProvider::class,
],
```

## Verify Installation

```bash
php artisan tinker
>>> app('tax')
=> AIArmada\Tax\Services\TaxCalculator {#...}
```

## Initial Data Setup

Create your first tax zone and rate:

```php
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxClass;

// Create tax classes
TaxClass::create([
    'name' => 'Standard',
    'slug' => 'standard',
    'is_default' => true,
    'is_active' => true,
]);

// Create a zone
$zone = TaxZone::create([
    'name' => 'Malaysia',
    'code' => 'MY',
    'countries' => ['MY'],
    'is_default' => true,
    'is_active' => true,
]);

// Create a rate
TaxRate::create([
    'zone_id' => $zone->id,
    'name' => 'SST',
    'tax_class' => 'standard',
    'rate' => 600, // 6%
    'is_active' => true,
]);
```

## With Filament Admin

If using the Filament admin panel:

```bash
composer require aiarmada/filament-tax
```

This provides ready-to-use resources for managing:
- Tax Zones
- Tax Rates
- Tax Classes
- Tax Exemptions

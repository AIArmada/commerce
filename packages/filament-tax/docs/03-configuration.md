---
title: Configuration
---

# Configuration

## Configuration File

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-tax-config
```

This creates `config/filament-tax.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'zones' => true,
        'classes' => true,
        'rates' => true,
        'exemptions' => true,
        'widgets' => true,
        'settings_page' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificates
    |--------------------------------------------------------------------------
    */
    'certificates' => [
        'disk' => env('TAX_CERTIFICATES_DISK', 'local'),
        'directory' => 'tax-exemptions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Tax',
        'settings_group' => 'Settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'navigation_sort' => [
            'zones' => 1,
            'classes' => 2,
            'rates' => 2,
            'exemptions' => 4,
        ],
    ],

    'pages' => [
        'navigation_sort' => [
            'settings' => 11,
        ],
    ],
];
```

## Plugin Configuration

The plugin supports fluent configuration in your panel provider:

```php
use AIArmada\FilamentTax\FilamentTaxPlugin;

FilamentTaxPlugin::make()
    ->zones(true)
    ->classes(true)
    ->rates(true)
    ->exemptions(true)
    ->widgets(true)
    ->settingsPage(true);
```

### Feature Toggles

#### `->zones(bool $enabled)`

Enable/disable the Tax Zones resource.

```php
FilamentTaxPlugin::make()->zones(true);
```

#### `->classes(bool $enabled)`

Enable/disable the Tax Classes resource.

```php
FilamentTaxPlugin::make()->classes(true);
```

#### `->rates(bool $enabled)`

Enable/disable the Tax Rates resource.

```php
FilamentTaxPlugin::make()->rates(true);
```

#### `->exemptions(bool $enabled)`

Enable/disable the Tax Exemptions resource.

```php
FilamentTaxPlugin::make()->exemptions(true);
```

#### `->widgets(bool $enabled)`

Enable/disable dashboard widgets:
- TaxStatsWidget
- ExpiringExemptionsWidget  
- ZoneCoverageWidget

```php
FilamentTaxPlugin::make()->widgets(true);
```

#### `->settingsPage(bool $enabled)`

Enable/disable the Tax Settings page.

```php
FilamentTaxPlugin::make()->settingsPage(true);
```

## Panel-Specific Configuration

Configure the plugin differently per panel:

```php
// Full admin access
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentTaxPlugin::make()
                    ->zones(true)
                    ->classes(true)
                    ->rates(true)
                    ->exemptions(true)
                    ->widgets(true)
                    ->settingsPage(true),
            ]);
    }
}

// Staff panel - no settings access
class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentTaxPlugin::make()
                    ->zones(true)
                    ->classes(true)
                    ->rates(true)
                    ->exemptions(true)
                    ->widgets(true)
                    ->settingsPage(false), // No settings
            ]);
    }
}

// Customer panel - exemptions only
class CustomerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentTaxPlugin::make()
                    ->zones(false)
                    ->classes(false)
                    ->rates(false)
                    ->exemptions(true) // Only exemptions
                    ->widgets(false)
                    ->settingsPage(false),
            ]);
    }
}
```

## Resource Navigation

Resource ordering within the Tax group is set in the `resources.navigation_sort`
key shown in the [Configuration File](#configuration-file) dump above
(`zones`, `classes`, `rates`, `exemptions`).

## Authorization Configuration

`FilamentTaxServiceProvider` always registers four model policies
(`TaxZonePolicy`, `TaxClassPolicy`, `TaxRatePolicy`, `TaxExemptionPolicy`).
Each one checks a `tax.*` permission and then an owner-scope check:

```php
// Permissions checked by the shipped policies
auth()->user()->can('tax.zones.view');    // and create / update / delete
auth()->user()->can('tax.classes.view');
auth()->user()->can('tax.rates.view');
auth()->user()->can('tax.exemptions.view');

// TaxSettings page
auth()->user()->can('tax.settings.view');
```

Seed those permissions in your app, or override the policies.

## Environment Variables

This package defines one env-backed key of its own:

```env
# Storage disk for uploaded exemption certificates (config/filament-tax.php)
TAX_CERTIFICATES_DISK=local
```

Everything else comes from the base tax package:

```env
TAX_ENABLED=true
TAX_DEFAULT_CURRENCY=MYR
TAX_PRICES_INCLUDE_TAX=false
TAX_OWNER_ENABLED=true
```

## Caching

The plugin doesn't implement its own caching. Consider caching at the application level:

```php
// Cache zone lookups
$zones = Cache::remember('tax-zones', 3600, function () {
    return TaxZone::active()->with('rates')->get();
});
```

## Localization

Override translations by publishing:

```bash
php artisan vendor:publish --tag=filament-tax-translations
```

This creates `lang/vendor/filament-tax/en/` with translation files.

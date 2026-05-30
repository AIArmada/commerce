---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel application with Filament v5
- `aiarmada/affiliates`

## Install via Composer

```bash
composer require aiarmada/filament-affiliates
```

`aiarmada/affiliates` is required and will be installed if missing.

## Register the admin plugin

Add the plugin to your Filament panel provider:

```php
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                FilamentAffiliatesPlugin::make(),
            ]);
    }
}
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-affiliates-config
```

This creates `config/filament-affiliates.php`.

## Publish Views (Optional)

To customize the views:

```bash
php artisan vendor:publish --tag=filament-affiliates-views
```

Views are published to `resources/views/vendor/filament-affiliates/`.

## Configure features and registration

Feature selection is config-driven via `config/filament-affiliates.php`.

- Admin resources/pages/widgets are controlled by `features.admin.*`
- Portal pages are controlled by `portal.features.*`

See [Configuration](03-configuration.md) for the exact keys.

## Affiliate Portal (Self-Service)

The package ships an optional dedicated panel provider:

- `AIArmada\FilamentAffiliates\AffiliatePanelProvider`

Register that provider in your app's provider list.

### Configure portal

```php
// config/filament-affiliates.php
'portal' => [
    'panel_id' => 'affiliate',
    'path' => 'affiliate',
    'domain' => null,
    'brand_name' => 'Affiliate Portal',
    'primary_color' => '#6366f1',
    'login_enabled' => true,
    'registration_enabled' => true,
    'auth_guard' => 'web',
],
```

### Portal Features

Configure which features are available to affiliates:

```php
'portal' => [
    'features' => [
        'dashboard' => true,
        'profile' => true,
        'links' => true,
        'programs' => true,
        'conversions' => true,
        'payouts' => true,
        'support_compliance' => true,
    ],
],
```

## Verify Installation

Visit your admin panel and check that affiliate resources/pages are visible according to your enabled admin features.

If portal is registered, visit the configured portal path and verify login/registration behavior per your portal config.

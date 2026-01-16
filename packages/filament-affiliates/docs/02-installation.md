---
title: Installation
---

# Installation

## Requirements

- PHP 8.4 or higher
- Laravel 11.x or higher
- Filament v5
- `aiarmada/affiliates` package

## Install via Composer

```bash
composer require aiarmada/filament-affiliates
```

This will automatically install `aiarmada/affiliates` as a dependency.

## Register the Plugin

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

## Plugin Options

### Navigation Group

```php
FilamentAffiliatesPlugin::make()
    ->navigationGroup('Sales & Marketing')
```

### Disable Specific Resources

```php
FilamentAffiliatesPlugin::make()
    ->resources([
        AffiliateResource::class,
        AffiliateConversionResource::class,
        // Omit resources you don't need
    ])
```

### Disable Widgets

```php
FilamentAffiliatesPlugin::make()
    ->widgets([
        AffiliateStatsWidget::class,
        // Only include widgets you want
    ])
```

### Custom Navigation Sort

```php
FilamentAffiliatesPlugin::make()
    ->navigationSort(50)
```

## Affiliate Portal (Self-Service)

The package includes an optional affiliate self-service portal.

### Enable Portal

```php
// config/filament-affiliates.php
'portal' => [
    'enabled' => true,
    'panel_id' => 'affiliate',
    'path' => 'affiliate',
    'brand_name' => 'Affiliate Portal',
],
```

### Register Portal Panel

Create a new panel provider:

```php
namespace App\Providers\Filament;

use AIArmada\FilamentAffiliates\AffiliatePanelProvider;

class AffiliatePanelProvider extends \Filament\PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('affiliate')
            ->path('affiliate')
            ->login()
            ->registration()
            ->pages([
                \AIArmada\FilamentAffiliates\Pages\Portal\PortalDashboard::class,
                \AIArmada\FilamentAffiliates\Pages\Portal\PortalConversions::class,
                \AIArmada\FilamentAffiliates\Pages\Portal\PortalPayouts::class,
                \AIArmada\FilamentAffiliates\Pages\Portal\PortalLinks::class,
            ]);
    }
}
```

### Portal Features

Configure which features are available to affiliates:

```php
'portal' => [
    'features' => [
        'dashboard' => true,
        'links' => true,
        'conversions' => true,
        'payouts' => true,
    ],
],
```

## Authorization

The package includes policies for all resources. Customize by extending:

```php
namespace App\Policies;

use AIArmada\FilamentAffiliates\Policies\AffiliatePolicy as BasePolicy;

class AffiliatePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view-affiliates');
    }
}
```

Register your custom policy:

```php
// AuthServiceProvider
protected $policies = [
    \AIArmada\Affiliates\Models\Affiliate::class => \App\Policies\AffiliatePolicy::class,
];
```

## Verify Installation

Visit your admin panel and look for the "Affiliates" navigation group.

You should see:
- Affiliates
- Conversions
- Payouts
- Programs
- Fraud Signals

And on the dashboard:
- Affiliate Stats widget

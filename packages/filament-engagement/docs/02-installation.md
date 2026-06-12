---
title: Installation
---

## Install

```bash
composer require aiarmada/filament-engagement
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\FilamentEngagement\FilamentEngagementServiceProvider" --tag="config"
```

## Register the plugin

Add the plugin to your Filament panel configuration:

```php
use AIArmada\FilamentEngagement\FilamentEngagementPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentEngagementPlugin::make(),
        ]);
}
```

## Configure widgets

If you want the engagement overview widget on your dashboard, add it to your dashboard widgets:

```php
use AIArmada\FilamentEngagement\Widgets\EngagementOverviewWidget;
```

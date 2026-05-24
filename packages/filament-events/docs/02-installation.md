---
title: Installation
---

# Installation

Install the package through Composer:

```bash
composer require aiarmada/filament-events
```

`aiarmada/events` is required by this package and should be installed automatically when Composer resolves dependencies.

## Run migrations

The Filament plugin depends on the core events tables, so make sure the `events` migrations have been run:

```bash
php artisan migrate
```

If you need to customize the core events package configuration first, see the [`aiarmada/events` installation guide](../../events/docs/02-installation.md).

## Register the plugin

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentEvents\FilamentEventsPlugin;
use Filament\Panel;
use Filament\PanelProvider;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentEventsPlugin::make(),
            ]);
    }
}
```

## Publish configuration

Publishing the config file is optional:

```bash
php artisan vendor:publish --tag=filament-events-config
```

This creates `config/filament-events.php`.

## Verify installation

After installation:

1. log into your Filament panel
2. confirm the `Events` navigation group appears
3. confirm the resources for event series, events, occurrences, venues, and registrations are visible

If the navigation is missing, see [Troubleshooting](99-troubleshooting.md).

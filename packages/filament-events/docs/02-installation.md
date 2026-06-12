---
title: Installation
---

## Install

```bash
composer require aiarmada/filament-events
```

## Publish configuration

```bash
php artisan vendor:publish --provider="AIArmada\FilamentEvents\FilamentEventsServiceProvider" --tag="config"
```

## Register the plugin

Add the plugin to your Filament panel configuration:

```php
use AIArmada\FilamentEvents\FilamentEventsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentEventsPlugin::make(),
        ]);
}
```

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `FILAMENT_EVENTS_NAVIGATION_GROUP` | `Events` | Navigation group label |

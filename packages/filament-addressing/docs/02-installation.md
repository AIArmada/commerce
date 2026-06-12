---
title: Filament Addressing Installation
---

## Requirements

- PHP 8.4+
- Laravel application matching the monorepo baseline
- Filament v5
- `aiarmada/addressing`

## Install Package

Add the package according to the monorepo package workflow.

If using Composer path repositories, make sure the package is registered in the root `composer.json`.

```bash
composer dump-autoload
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-addressing-config
```

This publishes:

```txt
config/filament-addressing.php
```

## Register The Plugin

Register the plugin in your Filament panel provider.

```php
<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AIArmada\FilamentAddressing\FilamentAddressingPlugin;
use Filament\Panel;
use Filament\PanelProvider;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentAddressingPlugin::make(),
            ]);
    }
}
```

## Run Core Addressing Setup First

This adapter does not ship address migrations or country data.

Install and migrate `aiarmada/addressing` first, then seed countries using the core package command or seeder.

Example:

```bash
php artisan migrate
php artisan address:seed-countries
```

## Verify

```bash
./vendor/bin/pest --parallel packages/filament-addressing/tests
./vendor/bin/phpstan analyse packages/filament-addressing/src --level=6
```

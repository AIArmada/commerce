---
title: Installation
---

# Installation

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 12.0+ |
| Filament | 5.0+ |
| aiarmada/docs | Required |

## Step 1: Install via Composer

```bash
composer require aiarmada/filament-docs
```

This installs `aiarmada/docs` automatically if it is not already present.

## Step 2: Register the Plugin

Add the plugin to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentDocs\FilamentDocsPlugin;
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
            ->plugins([
                FilamentDocsPlugin::make()
                    ->navigationGroup('Billing'),
            ]);
    }
}
```

## Step 3: Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=filament-docs-config
```

This creates `config/filament-docs.php` for customization.

## Step 4: Run Migrations

If you have not already migrated the underlying docs package:

```bash
php artisan migrate
```

## Verification

After installation, your Filament panel should expose:

- documents
- templates
- sequences
- email templates
- aging report
- pending approvals

## Troubleshooting

### Resources Not Showing

Confirm the plugin is registered and clear cached config:

```bash
php artisan config:clear
```

### Migration Issues

If migrations fail, confirm `aiarmada/docs` is installed and then run `php artisan migrate` again.

## Next Steps

- [Resources](02-resources.md) - Learn about the shipped resources
- [Configuration](03-configuration.md) - Customize navigation and behavior

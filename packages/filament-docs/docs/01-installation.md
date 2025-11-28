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

This will automatically install the `aiarmada/docs` dependency if not already present.

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
                FilamentDocsPlugin::make(),
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

If you haven't already set up the docs package:

```bash
php artisan migrate
```

## Verification

After installation, navigate to your Filament panel. You should see:

- **Documents** - In the navigation under "Documents" group
- **Templates** - For managing document templates

## Troubleshooting

### Resources Not Showing

Ensure the plugin is registered in your panel provider and you've cleared the config cache:

```bash
php artisan config:clear
php artisan filament:cache-components
```

### Migration Issues

If migrations fail, ensure the docs package migrations have run:

```bash
php artisan vendor:publish --tag=docs-migrations
php artisan migrate
```

## Next Steps

- [Resources](02-resources.md) - Learn about DocResource and DocTemplateResource
- [Configuration](03-configuration.md) - Customize navigation and settings

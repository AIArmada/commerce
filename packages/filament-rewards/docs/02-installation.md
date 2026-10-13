---
title: Filament rewards installation
---

# Filament rewards installation

```bash
composer require aiarmada/filament-rewards
```

Register the plugin on your panel:

```php
use AIArmada\FilamentRewards\FilamentRewardsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(FilamentRewardsPlugin::make());
}
```

Publish the config when you need to change navigation or owner types:

```bash
php artisan vendor:publish --tag=filament-rewards-config
```

Next: [configuration](03-configuration.md).

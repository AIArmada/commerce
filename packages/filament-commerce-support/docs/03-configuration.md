---
title: Configuration
---

## Config file

Published to `config/filament-commerce-support.php`:

```php
return [
    'navigation' => [
        'enabled' => env('FILAMENT_COMMERCE_NAVIGATION_ENABLED', true),
    ],
];
```

### `navigation.enabled`

Set to `false` to disable the entire navigation override system. The settings page will still be visible but `NavigationConfigurator` will not merge overrides into the commerce-support config.

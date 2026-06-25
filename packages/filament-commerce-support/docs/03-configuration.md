---
title: Configuration
---

## Config file

Published to `config/filament-commerce-support.php`:

```php
return [
    'navigation' => [
        'enabled' => env('FILAMENT_COMMERCE_NAVIGATION_ENABLED', true),
        'settings_group' => 'Settings',
        'sort' => 100,
        'permission' => 'manage-commerce-navigation',
    ],
];
```

### `navigation.enabled`

Set to `false` to disable the navigation override system and omit the settings page from the panel.

### Access and placement

- `navigation.settings_group` controls the settings page navigation group.
- `navigation.sort` controls its navigation order.
- `navigation.permission` is the Gate ability required to access the page. The default is `manage-commerce-navigation`.

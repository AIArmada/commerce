---
title: Configuration
---

## Config file

Published to `config/filament-commerce-support.php`:

```php
return [
    'navigation' => [
        'enabled' => env('FILAMENT_COMMERCE_NAVIGATION_ENABLED', true),
        'group' => 'Commerce',
        'settings_group' => 'Settings',
        'sort' => 100,
        'permission' => 'manage-commerce-navigation',
        'icons' => [
            'currencies' => 'heroicon-o-currency-dollar',
            'languages' => 'heroicon-o-language',
            'timezones' => 'heroicon-o-globe-alt',
        ],
    ],
    'resources' => [
        'currencies' => ['enabled' => true, 'read_only' => true],
        'languages' => ['enabled' => true, 'read_only' => true],
        'timezones' => ['enabled' => true, 'read_only' => true],
    ],
];
```

### `navigation.enabled`

Set to `false` to disable the navigation override system and omit the settings page from the panel.

### Access and placement

- `navigation.group` controls the reference-data resources navigation group.
- `navigation.settings_group` controls the settings page navigation group.
- `navigation.sort` controls its navigation order.
- `navigation.permission` is the Gate ability required to access the page. The default is `manage-commerce-navigation`.
- `navigation.icons.*` override the reference-data resource icons.

### Reference-data resources

The plugin registers the `currencies`, `languages`, and `timezones`
resources automatically. Set `resources.<name>.enabled` to `false` to omit
one, or `read_only` to `false` to allow editing seed data (not recommended).

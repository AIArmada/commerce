---
title: Configuration
---

# Configuration

All package options live in `config/filament-growth.php`.

## Configuration structure

```php
return [
    'navigation_group' => 'Growth',

    'features' => [
        'dashboard' => true,
        'results' => true,
        'widgets' => true,
        'experiments' => true,
        'variants' => true,
    ],

    'resources' => [
        'navigation_sort' => [
            'dashboard' => 10,
            'results' => 11,
            'experiments' => 20,
            'variants' => 21,
        ],
    ],
];
```

## Navigation

### `navigation_group`

Controls the Filament navigation group used by the dashboard, results page, and resources.

## Features

### `features.dashboard`

Registers the `GrowthDashboard` page in the panel.

### `features.results`

Registers the `ExperimentResultsPage` and enables dashboard header actions that link to results.

### `features.widgets`

Allows `GrowthDashboard` to attach:

- `GrowthStatsWidget`
- `ExperimentWinnersWidget`

This flag only affects widget registration on the dashboard page.

### `features.experiments`

Registers `ExperimentResource`.

### `features.variants`

Registers `VariantResource`.

## Resources

### `resources.navigation_sort`

Controls the order of registered navigation items:

- `dashboard`
- `results`
- `experiments`
- `variants`

## Example custom configuration

```php
<?php

return [
    'navigation_group' => 'Optimization',

    'features' => [
        'dashboard' => true,
        'results' => true,
        'widgets' => true,
        'experiments' => true,
        'variants' => false,
    ],

    'resources' => [
        'navigation_sort' => [
            'dashboard' => 30,
            'results' => 31,
            'experiments' => 40,
            'variants' => 41,
        ],
    ],
];
```

## Related reading

- [Installation](./02-installation.md)
- [Usage](./04-usage.md)
- [`growth` configuration](../../growth/docs/03-configuration.md)
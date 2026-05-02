---
title: Configuration
---

# Configuration

## Publish Configuration

```bash
php artisan vendor:publish --tag="filament-chip-config"
```

## Configuration File

`config/filament-chip.php`:

```php
return [
    'navigation' => [
        'group' => 'CHIP Operations',
        'badge_color' => 'primary',
    ],

    'polling_interval' => '45s',

    'tables' => [
        'created_on_format' => 'Y-m-d H:i:s',
        'updated_on_format' => 'Y-m-d H:i:s',
        'amount_precision' => 2,
    ],

    'default_currency' => 'MYR',

    'enforce_owner_scoping' => true,

    'resources' => [
        'navigation_sort' => [
            'purchases' => 10,
            'payments' => 20,
            'clients' => 30,
            'bank_accounts' => 40,
            'send_instructions' => 50,
            'company_statements' => 60,
        ],
    ],
];
```

## Navigation

```php
'navigation' => [
    'group' => 'CHIP Operations',
    'badge_color' => 'primary',
],
```

- `group` controls the sidebar navigation group used by CHIP resources/pages.
- `badge_color` is used by navigation badge styling where badges are rendered.

## Table Behavior

```php
'polling_interval' => '45s',

'tables' => [
    'created_on_format' => 'Y-m-d H:i:s',
    'updated_on_format' => 'Y-m-d H:i:s',
    'amount_precision' => 2,
],
```

- `polling_interval` controls auto-refresh for polling-enabled tables/widgets.
- `created_on_format` / `updated_on_format` define date display formatting.
- `amount_precision` controls decimal precision for rendered money values.

## Feature Settings

```php
'default_currency' => 'MYR',
'enforce_owner_scoping' => true,
```

- `default_currency` is the fallback currency used by UI formatting helpers.
- `enforce_owner_scoping` hardens resource/page queries to stay owner-scoped when owner mode is enabled upstream.

## Resource Navigation Sort

```php
'resources' => [
    'navigation_sort' => [
        'purchases' => 10,
        'payments' => 20,
        'clients' => 30,
        'bank_accounts' => 40,
        'send_instructions' => 50,
        'company_statements' => 60,
    ],
],
```

These values control sidebar ordering when the corresponding resource is registered in the panel.

## Plugin Registration

```php
use AIArmada\FilamentChip\FilamentChipPlugin;

$panel->plugin(FilamentChipPlugin::make());
```

By default, the plugin registers:

- Page: `AnalyticsDashboardPage`
- Resources: `PurchaseResource`, `ClientResource`
- Widgets: `ChipStatsWidget`, `RevenueChartWidget`, `RecentTransactionsWidget`

Optional resources/widgets can be registered explicitly in your panel provider.

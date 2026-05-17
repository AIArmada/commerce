---
title: Configuration
---

# Configuration

All package options live in `config/growth.php`.

## Configuration structure

```php
return [
    'database' => [
        'table_prefix' => 'growth_',
        'json_column_type' => commerce_json_column_type('growth', 'json'),
        'tables' => [
            'experiments' => 'growth_experiments',
            'variants' => 'growth_variants',
            'assignments' => 'growth_assignments',
        ],
    ],

    'defaults' => [
        'module_type' => 'ab_test',
        'winner_metric' => 'revenue_per_visitor',
        'presets' => [
            // ab_test, sales_page_test, funnel_test, pricing_test
        ],
    ],

    'features' => [
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
        ],
        'preset_modules' => [
            'enabled' => true,
        ],
    ],

    'integrations' => [
        'signals' => [
            'enabled' => true,
            'checkout_started_event_name' => 'checkout.started',
            'purchase_event_name' => 'order.paid',
            'refund_event_name' => 'order.refunded',
        ],
    ],
];
```

## Database

### `database.table_prefix`

The table prefix used when a table name is not overridden in the `tables` map.

### `database.json_column_type`

Controls the JSON column type used by growth migrations. Leave this as `commerce_json_column_type('growth', 'json')` unless you need package-specific `jsonb` behavior on PostgreSQL.

### `database.tables`

Override individual table names:

- `experiments`
- `variants`
- `assignments`

## Defaults

### `defaults.module_type`

The fallback experiment module type when none is provided.

Growth only applies this fallback when `module_type` is blank. If you pass a custom module type string that is not one of the built-in presets, that explicit value is preserved.

### `defaults.winner_metric`

The fallback winner metric when an experiment does not define one explicitly.

### `defaults.presets`

Preset definitions keyed by module type. Each preset can provide:

- `goal_event_name`
- `goal_event_category`
- `winner_metric`
- `settings`

When preset modules are enabled, blank experiment fields are hydrated from the selected preset during creation.

Custom module types that do not match a built-in preset still receive the generic goal and winner defaults, but their `module_type` value is not rewritten.

The built-in presets ship with these settings shapes:

- `ab_test`: empty `settings`
- `sales_page_test`: `cta_event_name`, `entry_paths`, `destination_urls`
- `funnel_test`: `funnel_steps[]` with `label`, `event_name`, and `event_category`
- `pricing_test`: `checkout_event_name`, `price_labels`

## Features

### `features.owner`

Owner scoping controls for growth models:

```php
'owner' => [
    'enabled' => true,
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

- `enabled`: apply owner scoping to `Experiment`, `Variant`, and `Assignment`
- `include_global`: whether readable owner-scoped queries may include global rows
- `auto_assign_on_create`: automatically stamp the current owner on new rows when owner columns are omitted

### `features.preset_modules.enabled`

Enables module presets and preset-aware defaults for new experiments.

## Integrations

### `integrations.signals.enabled`

Turns Signals event enrichment on or off.

### Signals event names

These keys control which event names the aggregator and enrichment flow care about:

- `checkout_started_event_name`
- `purchase_event_name`
- `refund_event_name`

`purchase_event_name` is also the fallback goal event name used when an experiment does not provide one explicitly.

## Example custom configuration

```php
<?php

return [
    'database' => [
        'table_prefix' => 'growth_',
        'json_column_type' => commerce_json_column_type('growth', 'jsonb'),
        'tables' => [
            'experiments' => 'growth_experiments',
            'variants' => 'growth_variants',
            'assignments' => 'growth_assignments',
        ],
    ],

    'defaults' => [
        'module_type' => 'sales_page_test',
        'winner_metric' => 'conversion_rate',
        'presets' => [
            'sales_page_test' => [
                'goal_event_name' => 'checkout.completed',
                'goal_event_category' => 'conversion',
                'winner_metric' => 'conversion_rate',
                'settings' => [
                    'cta_event_name' => 'hero_cta_click',
                    'entry_paths' => ['/offers/pro'],
                    'destination_urls' => ['/checkout/pro'],
                ],
            ],
        ],
    ],

    'features' => [
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
        ],
        'preset_modules' => [
            'enabled' => true,
        ],
    ],

    'integrations' => [
        'signals' => [
            'enabled' => true,
            'checkout_started_event_name' => 'checkout.started',
            'purchase_event_name' => 'checkout.completed',
            'refund_event_name' => 'order.refunded',
        ],
    ],
];
```

## Related reading

- [Installation](./02-installation.md)
- [Usage](./04-usage.md)
- [`commerce-support` owner scoping docs](../../commerce-support/docs/04-multi-tenancy.md)
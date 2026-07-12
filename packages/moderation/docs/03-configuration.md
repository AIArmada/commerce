---
title: Configuration
---

# Configuration

`config/moderation.php` controls the moderation tables, JSON column type, owner mode, and default block duration.

## Database

```php
'database' => [
    'table_prefix' => env('MODERATION_TABLE_PREFIX', 'moderation_'),
    'tables' => [
        'blocks' => env('MODERATION_TABLE_BLOCKS', $tablePrefix . 'blocks'),
        'moderation_actions' => env('MODERATION_TABLE_ACTIONS', $tablePrefix . 'actions'),
    ],
],
```

- `database.table_prefix` controls the default moderation table prefix
- `database.tables.blocks` and `database.tables.moderation_actions` can be overridden individually
- JSON column type is controlled by `commerce_json_column_type('moderation', 'jsonb')`

## Features

```php
'features' => [
    'owner' => [
        'enabled' => env('MODERATION_OWNER_ENABLED', true),
    ],
],
```

- `features.owner.enabled` turns owner-aware validation on or off for tenant-owned models
- When enabled, blocks and moderation actions inherit the current owner and are isolated by the global owner scope

## Defaults

```php
'defaults' => [
    'block_duration_days' => (int) env('MODERATION_BLOCK_DURATION_DAYS', 30),
],
```

- `defaults.block_duration_days` sets the fallback expiry for new blocks when no explicit expiry is passed

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

## Owner scoping

```php
'owner' => [
    'enabled' => env('MODERATION_OWNER_ENABLED', true),
    'include_global' => env('MODERATION_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('MODERATION_OWNER_AUTO_ASSIGN_ON_CREATE', true),
],
```

- `owner.enabled` turns owner-aware validation and scoping on or off
- `owner.include_global` explicitly includes global moderation rows in owner queries
- `owner.auto_assign_on_create` controls inheritance of the current owner on new rows
- When enabled, blocks and moderation actions inherit the current owner and are isolated by the global owner scope

## Defaults

```php
'defaults' => [
    'block_duration_days' => (int) env('MODERATION_BLOCK_DURATION_DAYS', 30),
],
```

- `defaults.block_duration_days` sets the fallback expiry for new blocks when no explicit expiry is passed

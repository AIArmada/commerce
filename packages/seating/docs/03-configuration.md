---
title: Seating Configuration
---

# Configuration

Published to `config/seating.php`.

```php
return [
    'database' => [
        'tables' => [
            'seat_maps' => env('SEATING_TABLE_SEAT_MAPS', 'seat_maps'),
            'seat_sections' => env('SEATING_TABLE_SEAT_SECTIONS', 'seat_sections'),
            'seats' => env('SEATING_TABLE_SEATS', 'seats'),
            'seat_holds' => env('SEATING_TABLE_SEAT_HOLDS', 'seat_holds'),
            'seat_allocations' => env('SEATING_TABLE_SEAT_ALLOCATIONS', 'seat_allocations'),
        ],
    ],

    'holds' => [
        'ttl_minutes' => (int) env('SEATING_HOLD_TTL_MINUTES', 15),
    ],

    'owner' => [
        'enabled' => env('SEATING_OWNER_ENABLED', true),
        'include_global' => env('SEATING_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('SEATING_OWNER_AUTO_ASSIGN_ON_CREATE', true),
    ],

    'scheduling' => [
        'release_expired_holds' => env('SEATING_RELEASE_EXPIRED_HOLDS', true),
    ],
];
```

## `holds.ttl_minutes`

Duration (in minutes) that a seat hold remains active. After this period the hold is eligible for release via the `seating:release-expired-holds` command.

## `owner`

Owner scoping configuration. When enabled, all seating models are scoped to the current owner context.

- `enabled`: Enable owner scoping (default: `true`).
- `include_global`: Include ownerless (global) records in owner-scoped queries (default: `false`).
- `auto_assign_on_create`: Automatically assign the current owner to new records (default: `true`).

JSON column type is controlled by `commerce_json_column_type('seating', 'json')`. Set `COMMERCE_JSON_COLUMN_TYPE=jsonb` in your `.env` to override.

## `scheduling.release_expired_holds`

Whether to register the scheduled task that releases expired seat holds (default: `true`).

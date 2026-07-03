---
title: Configuration
---

## Configuration file

The `config/events.php` file controls all Events package behavior.

### Database

```php
$tablePrefix = env('EVENTS_TABLE_PREFIX', '');

'database' => [
    'json_column_type' => env('EVENTS_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'events' => env('EVENTS_TABLE_EVENTS', $tablePrefix . 'events'),
        'event_occurrences' => env('EVENTS_TABLE_OCCURRENCES', $tablePrefix . 'event_occurrences'),
        // ... all 40+ table names configurable via env
    ],
]
```

Every table name is individually configurable via environment variables, allowing collision-free coexistence with other packages.

### Free-Only Mode

```php
'features' => [
    'free_only' => [
        'default_registration_mode' => env('EVENTS_DEFAULT_REGISTRATION_MODE', 'required'),
        'auto_issue_passes_for_free' => env('EVENTS_AUTO_ISSUE_PASSES_FOR_FREE', true),
        'auto_derive_pricing_from_ticket_types' => env('EVENTS_AUTO_DERIVE_PRICING', true),
        'open_door_mode' => env('EVENTS_OPEN_DOOR_MODE', 'block'),
    ],
],
```

| Key | Description |
|---|---|
| `default_registration_mode` | Fallback when no explicit `registration_mode` is set on an event. One of `required`, `optional`, `none`. |
| `auto_issue_passes_for_free` | Default value for `issue_passes_for_free` when it is `null` at the event/occurrence/session level |
| `auto_derive_pricing_from_ticket_types` | When `true`, pricing mode is automatically inferred from ticket type prices (all free → Free, all paid → Paid, mixed → Mixed) |
| `open_door_mode` | Default behavior for events with `registration_mode = none`. One of `block`, `walk_in`, `headcount`. |

### Seating

```php
'features' => [
    'auto_allocate_seats' => env('EVENTS_AUTO_ALLOCATE_SEATS', true),
    'auto_revoke_passes_on_cancel' => env('EVENTS_AUTO_REVOKE_PASSES_ON_CANCEL', true),
],
```

| Key | Description |
|---|---|
| `auto_allocate_seats` | When enabled, passes for ticket types with a seating mode trigger automatic seat allocation on issue. Requires `aiarmada/ticketing` and `aiarmada/seating`. |
| `auto_revoke_passes_on_cancel` | When enabled, cancelling a registration automatically revokes all its passes. |

### Paid Registrations

```php
'features' => [
    'enforce_scope_capacity_on_paid_registrations' => env('EVENTS_ENFORCE_SCOPE_CAPACITY_PAID', false),
]
```

When enabled, paid registration flows stop before creating new confirmed registrations if the target occurrence or session has no remaining capacity. Replaying an order item that already has registrations remains idempotent.

### Owner Scoping

```php
'features' => [
    'owner' => [
        'enabled' => env('EVENTS_OWNER_ENABLED', true),
        'include_global' => env('EVENTS_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('EVENTS_OWNER_AUTO_ASSIGN', true),
    ],
]
```

Controls multi-tenancy behavior. When enabled, event roots and children, series/template children, submissions, and polymorphic workflow records are scoped through an owner-safe parent. Global records are available when `include_global` is enabled, but mutating them still requires explicit global context.

### Defaults

```php
'defaults' => [
    'timezone' => env('EVENTS_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
]
```

### Codes

```php
'codes' => [
    'registration_prefix' => env('EVENTS_REGISTRATION_PREFIX', 'REG'),
    'registration_length' => (int) env('EVENTS_REGISTRATION_LENGTH', 10),
]
```

Controls auto-generated registration number format.

### Inventory Backfill

```php
'features' => [
    'inventory' => [
        'default_location_id' => env('EVENTS_DEFAULT_INVENTORY_LOCATION', 'default'),
        'auto_register_quotas_on_migrate' => env('EVENTS_AUTO_REGISTER_QUOTAS', true),
    ],
]
```

`default_location_id` is used by the legacy quota migration to resolve the inventory location token. Runtime ticket-type syncing uses the inventory package's default location and only seeds positive quotas.

### Lifecycle

```php
'lifecycle' => [
    'occurrence' => [
        'registration_accepting_statuses' => ['scheduled', 'published', 'live'],
        'check_in_accepting_statuses' => ['scheduled', 'published', 'live'],
        'walk_in_accepting_statuses' => ['scheduled', 'published', 'live'],
    ],
    'registration' => [
        'check_in_allowed_statuses' => ['confirmed'],
        'capacity_blocking_statuses' => ['pending', 'confirmed', 'checked_in', 'no_show'],
        'terminal_statuses' => ['checked_in', 'cancelled', 'refunded', 'no_show'],
        'auto_promote_waitlist' => env('EVENTS_AUTO_PROMOTE_WAITLIST', false),
    ],
]
```

Controls which statuses allow registration, check-in, and walk-in. `capacity_blocking_statuses` determines which registration statuses consume occurrence capacity. Statuses are managed through `spatie/laravel-model-states` — transitions are defined in each state base class's `config()` method under `States/`.

### Synchronization

```php
'sync' => [
    'attributes_to_metadata' => env('EVENTS_SYNC_ATTRIBUTES_TO_METADATA', true),
    'audiences_to_metadata' => env('EVENTS_SYNC_AUDIENCES_TO_METADATA', true),
    'time_expressions_to_metadata' => env('EVENTS_SYNC_TIME_EXPRESSIONS_TO_METADATA', true),
    'classifications_to_facets' => env('EVENTS_SYNC_CLASSIFICATIONS_TO_FACETS', true),
    'audiences_to_facets' => env('EVENTS_SYNC_AUDIENCES_TO_FACETS', true),
    'build_search_documents' => env('EVENTS_SYNC_BUILD_SEARCH_DOCUMENTS', false),
],

'attribute_sync' => [
    'attribute_keys' => null,
    'audience_types' => null,
    'taxonomy_codes' => null,
    'always_rebuild' => env('EVENTS_ATTRIBUTE_SYNC_ALWAYS_REBUILD', true),
],
```

These flags control the denormalized metadata projection and the search document rebuild pipeline for events, occurrences, and sessions.

- `attributes_to_metadata`, `audiences_to_metadata`, and `time_expressions_to_metadata` keep the model `metadata` JSON blob in sync with the relation tables for each supported scope.
- `classifications_to_facets` and `audiences_to_facets` add relation-backed facets to the search document payload.
- `build_search_documents` enables automatic search document creation and removal for events, occurrences, and sessions. When it is disabled, the observers short-circuit and no documents are written.
- `attribute_sync.attribute_keys`, `audience_types`, and `taxonomy_codes` let you narrow the synced records. Leave them `null` to sync everything.
- `always_rebuild` removes stale non-reserved keys from `Event.metadata` when the attribute projection is rebuilt.

### Resolvers (extensibility seams)

```php
'classifications' => ['resolver' => null],
'references' => ['resolver' => null],
'timezone' => ['display_timezone_resolver' => null],
'schedule' => ['resolver' => null],
'search' => [
    'payload_resolver' => null,
    'engine' => null,
    'indexer' => null,
    'queue_indexing' => env('EVENTS_SEARCH_QUEUE_INDEXING', false),
    'queue_connection' => env('EVENTS_SEARCH_QUEUE_CONNECTION'),
    'queue_name' => env('EVENTS_SEARCH_QUEUE_NAME'),
],
'change_notices' => [
    'audience_resolver' => null,
    'notification_dispatcher' => null,
],
```

Each resolver can be bound to a custom class for domain-specific behavior.

`search.indexer` defaults to the package's built-in `EventSearchDocumentBuilder` when left `null`. That builder maintains search documents for events, occurrences, and sessions. Set it to `AIArmada\Events\Resolvers\NullEventSearchIndexer` if you want to disable automatic search indexing explicitly, or point it at a custom indexer implementation.

### Integrations

```php
'integrations' => [
    'product_model' => class_exists(...) ? Product::class : null,
    'customer_model' => class_exists(...) ? Customer::class : null,
    'order_model' => class_exists(...) ? Order::class : null,
    'addressing_enabled' => env('EVENTS_ADDRESSING_ENABLED', false),
    'checkout_intent_resolver' => null,
    'order_item_fulfillment_resolver' => null,
]
```

Auto-detects commerce packages. When related packages are installed, integration features are automatically enabled. Set `addressing_enabled` to `true` only when the `aiarmada/addressing` package is installed and its migrations have been run. Custom resolvers can override default behavior.

### Notifications

```php
'notifications' => [
    'welcome' => [
        'enabled' => env('EVENTS_WELCOME_NOTIFICATION_ENABLED', true),
        'from_address' => env('EVENTS_WELCOME_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('EVENTS_WELCOME_FROM_NAME', env('MAIL_FROM_NAME')),
        'event_name' => env('EVENTS_WELCOME_EVENT_NAME', env('APP_NAME')),
        'brand_name' => env('EVENTS_WELCOME_BRAND_NAME', env('APP_NAME')),
    ],
    'ticket' => [
        'enabled' => env('EVENTS_TICKET_NOTIFICATION_ENABLED', true),
        'from_address' => env('EVENTS_TICKET_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('EVENTS_TICKET_FROM_NAME', env('MAIL_FROM_NAME')),
        'event_name' => env('EVENTS_TICKET_EVENT_NAME', env('APP_NAME')),
        'brand_name' => env('EVENTS_TICKET_BRAND_NAME', env('APP_NAME')),
    ],
]
```

The welcome notification is sent when a registration is approved. The ticket notification is sent after passes are issued.

---
title: Configuration
---

## Configuration file

The `config/events.php` file controls all Events package behavior.

### Database

```php
'database' => [
    'table_prefix' => env('EVENTS_TABLE_PREFIX', ''),
    'json_column_type' => env('EVENTS_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'events' => env('EVENTS_TABLE_EVENTS', $tablePrefix . 'events'),
        'event_occurrences' => env('EVENTS_TABLE_OCCURRENCES', $tablePrefix . 'event_occurrences'),
        // ... all 40+ table names configurable via env
    ],
]
```

Every table name is individually configurable via environment variables, allowing collision-free coexistence with other packages.

### Owner Scoping

```php
'owner' => [
    'enabled' => env('EVENTS_OWNER_ENABLED', false),
    'include_global' => env('EVENTS_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('EVENTS_OWNER_AUTO_ASSIGN', true),
]
```

Controls multi-tenancy behavior. When enabled, all queries are scoped to the current resolved owner. Global records are available when `include_global` is enabled.

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

Controls which statuses allow registration, check-in, and walk-in. `capacity_blocking_statuses` determines which registration statuses consume occurrence capacity.

### Resolvers (extensibility seams)

```php
'classifications' => ['resolver' => null],
'assets' => ['resolver' => null],
'references' => ['resolver' => null],
'timezone' => ['display_timezone_resolver' => null],
'schedule' => ['resolver' => null],
'search' => ['payload_resolver' => null, 'engine' => null],
'change_notices' => [
    'audience_resolver' => null,
    'notification_dispatcher' => null,
],
```

Each resolver can be bound to a custom class for domain-specific behavior.

### Moderation

```php
'moderation' => [
    'actions' => [
        'submit' => ['from' => ['draft', 'pending', 'approved', 'changes_requested', 'rejected'], 'to' => 'pending'],
        'approve' => ['from' => ['pending', 'changes_requested'], 'to' => 'approved'],
        'reject'  => ['from' => ['pending', 'approved', 'changes_requested'], 'to' => 'rejected'],
        'cancel'  => ['from' => ['pending', 'approved', 'changes_requested', 'rejected'], 'to' => 'pending'],
    ],
    'reason_codes' => [
        'approved_for_publish' => ['label' => 'Approved for Publish'],
        'needs_more_information' => ['label' => 'Needs More Information', 'note_required' => true],
    ],
]
```

Defines allowed moderation transitions and reason codes. Each action specifies valid `from` states, target `to` state, and whether notes/reasons are required.

### Integrations

```php
'integrations' => [
    'product_model' => class_exists(...) ? Product::class : null,
    'customer_model' => class_exists(...) ? Customer::class : null,
    'order_model' => class_exists(...) ? Order::class : null,
    'checkout_intent_resolver' => null,
    'order_item_fulfillment_resolver' => null,
]
```

Auto-detects commerce packages. When related packages are installed, integration features are automatically enabled. Custom resolvers can override default behavior.

### Notifications

```php
'notifications' => [
    'welcome' => [
        'enabled' => env('EVENTS_WELCOME_NOTIFICATION_ENABLED', true),
        'from_address' => env('EVENTS_WELCOME_FROM_ADDRESS', 'info@unfairadvantage.my'),
        'from_name' => env('EVENTS_WELCOME_FROM_NAME'),
        'event_name' => env('EVENTS_WELCOME_EVENT_NAME', 'AI Awakening'),
        'brand_name' => env('EVENTS_WELCOME_BRAND_NAME', 'Unfair Advantage'),
    ],
    'ticket' => [
        'enabled' => env('EVENTS_TICKET_NOTIFICATION_ENABLED', true),
        'from_address' => env('EVENTS_TICKET_FROM_ADDRESS', 'info@unfairadvantage.my'),
        'from_name' => env('EVENTS_TICKET_FROM_NAME'),
        'event_name' => env('EVENTS_TICKET_EVENT_NAME', 'AI Awakening'),
        'brand_name' => env('EVENTS_TICKET_BRAND_NAME', 'Unfair Advantage'),
    ],
]
```

The welcome notification is sent when a registration is approved. The ticket notification is sent after passes are issued.

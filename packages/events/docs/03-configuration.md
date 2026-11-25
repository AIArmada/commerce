---
title: Configuration
---

## Configuration file

The `config/events.php` file controls all Events package behavior.

### Model overrides

```php
'models' => [
    'event' => null,
    'occurrence' => null,
    'session' => null,
    'registration' => null,
    'registration_participant' => null,
    'registration_question' => null,
    'attendance' => null,
    'walk_in' => null,
    'headcount_log' => null,
    'submission' => null,
    'escalation' => null,
    'approval_request' => null,
    'management_assignment' => null,
    'change_log' => null,
    'update' => null,
    'search_document' => null,
    'venue' => null,
    'venue_space' => null,
    'location' => null,
    'facility' => null,
    'classification' => null,
    'attribute' => null,
    'audience' => null,
],
```

Every key ships as `null`. When the package boots, each feature's `FeatureModule`
registers its default class only if the key is still `null`, so an application
override wins. Resolve configured classes at runtime through
`AIArmada\Events\Platform\ModelResolver` — never import the concrete package
model when the host may swap it:

```php
use AIArmada\Events\Platform\ModelResolver;

$eventClass = ModelResolver::eventClass();
$venueClass = ModelResolver::venueClass();
$registrationClass = ModelResolver::registrationClass();

// Generic key access (throws for unknown keys):
$submissionClass = ModelResolver::resolve('submission');
```

> **warning**
>
> `ModelResolver` throws a `RuntimeException` when a key is not configured or
> does not resolve to an Eloquent model. Disabling an optional feature module
> therefore disables every resolver call for its keys.

### Database

```php
$tablePrefix = env('EVENTS_TABLE_PREFIX', '');

'database' => [
    'json_column_type' => env('EVENTS_JSON_COLUMN_TYPE', 'jsonb'),
    'tables' => [
        'events' => env('EVENTS_TABLE_EVENTS', $tablePrefix . 'events'),
        'event_occurrences' => env('EVENTS_TABLE_OCCURRENCES', $tablePrefix . 'event_occurrences'),
        // ... every table name is configurable via env
    ],
]
```

Every table name is individually configurable via environment variables,
allowing collision-free coexistence with other packages. Migrations resolve
the JSON column type through `commerce_json_column_type('events', 'jsonb')`,
which prefers `EVENTS_JSON_COLUMN_TYPE`, then the shared
`COMMERCE_JSON_COLUMN_TYPE`, then this config value.

### Media profiles

The package models use Spatie Media Library through configurable media
profiles (`Platform\RegistersEventMedia`). The default profiles provide
`cover`, `poster`, and `gallery` collections for the event model, and a
`cover` collection for occurrences and sessions. Applications can replace the
collections or conversions without editing package models:

```php
'media' => [
    'profiles' => [
        'occurrence' => [
            'collections' => [
                'cover' => [
                    'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
                    'responsive' => true,
                    'single_file' => true,
                ],
            ],
            'conversions' => [
                'thumb' => [
                    'collections' => ['cover'],
                    'fit' => 'max',
                    'width' => 1920,
                    'height' => 1080,
                    'format' => 'webp',
                ],
            ],
        ],
    ],
],
```

Collection options include `disk`, `mimes`, `responsive`, `single_file`,
`limit`, `fallback_url`, and `fallback_path`. Conversion options include
`collections`, `fit`, `width`, `height`, `sharpen`, and `format`. The
`mediaRecords()` relation remains the package's structured `EventMedia`
relation; Spatie's file relation remains `media()`.

### Defaults

```php
'defaults' => [
    'currency' => env('EVENTS_DEFAULT_CURRENCY', env('TICKETING_DEFAULT_CURRENCY', 'MYR')),
    'timezone' => env('EVENTS_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
]
```

`currency` is used when issuing registration passes without an explicit
registration currency. `timezone` controls the package fallback display and
scheduling timezone.

### Features

`config/events.php` has a single `features` array. The `Free-Only Mode`,
`Seating`, `Paid Registrations`, and `Owner Scoping` sections below each show
one part of it — they are not standalone blocks, and replacing the whole
array with one of them drops the sibling keys.

#### Free-Only Mode

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

#### Seating and pass automation

```php
'features' => [
    'auto_issue_passes' => env('EVENTS_AUTO_ISSUE_PASSES', true),
    'auto_allocate_seats' => env('EVENTS_AUTO_ALLOCATE_SEATS', true),
    'auto_revoke_passes_on_cancel' => env('EVENTS_AUTO_REVOKE_PASSES_ON_CANCEL', true),
],
```

| Key | Description |
|---|---|
| `auto_issue_passes` | When enabled, free registrations issue passes automatically. Requires `aiarmada/ticketing`. |
| `auto_allocate_seats` | When enabled, passes for ticket types with a seating mode trigger automatic seat allocation on issue. Requires `aiarmada/ticketing` and `aiarmada/seating`. |
| `auto_revoke_passes_on_cancel` | When enabled, cancelling a registration automatically revokes all its passes. |

#### Paid registrations

```php
'features' => [
    'enforce_scope_capacity_on_paid_registrations' => env('EVENTS_ENFORCE_SCOPE_CAPACITY_PAID', false),
]
```

When enabled, paid registration flows stop before creating new confirmed
registrations if the target occurrence or session has no remaining capacity.
Replaying an order item that already has registrations remains idempotent.

#### Commerce refunds

```php
'features' => [
    'commerce' => [
        'refunds_enabled_by_default' => (bool) env('EVENTS_REFUNDS_ENABLED_BY_DEFAULT', false),
        'refund_self_service_hours' => (int) env('EVENTS_REFUND_SELF_SERVICE_HOURS', 48),
    ],
],
```

Consumed by the default `EventRefundEligibility` implementation to gate
self-service refunds.

#### Owner Scoping

```php
'features' => [
    'owner' => [
        'enabled' => env('EVENTS_OWNER_ENABLED', true),
        'include_global' => env('EVENTS_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('EVENTS_OWNER_AUTO_ASSIGN', true),
    ],
]
```

Controls multi-tenancy behavior. When enabled, event roots, event-bound
children, series/template children, submissions, and polymorphic workflow
records are scoped through an owner-safe parent. Global records are available
when `include_global` is enabled, but mutating them still requires explicit
global context.

### Codes

```php
'codes' => [
    'registration_prefix' => env('EVENTS_REGISTRATION_PREFIX', 'REG'),
]
```

Controls the prefix used for auto-generated registration numbers. There is no
length config key; the numeric suffix is generated by the package.

### Lifecycle

```php
'lifecycle' => [
    'event' => [
        'registration_blocked_statuses' => ['cancelled', 'completed', 'archived', 'expired', 'voided'],
    ],
    'session' => [
        'registration_accepting_statuses' => ['scheduled', 'published', 'live'],
    ],
    'occurrence' => [
        'registration_accepting_statuses' => ['scheduled', 'published', 'live'],
        'check_in_accepting_statuses' => ['scheduled', 'published', 'live'],
        'walk_in_accepting_statuses' => ['scheduled', 'published', 'live'],
    ],
    'registration' => [
        'check_in_allowed_statuses' => ['confirmed'],
        'capacity_blocking_statuses' => ['pending', 'confirmed', 'refund_pending', 'checked_in'],
        'terminal_statuses' => ['checked_in', 'cancelled', 'refunded', 'no_show'],
        'auto_promote_waitlist' => env('EVENTS_AUTO_PROMOTE_WAITLIST', false),
    ],
],
```

Controls which statuses allow registration, check-in, and walk-in. The
`Platform\LifecyclePolicy` class reads these keys (`canAcceptRegistrations`,
`canCheckIn`, `canWalkIn`, `isCapacityBlocking`, `isTerminal`,
`shouldAutoPromoteWaitlist`). Registrations are accepted only when the event
is not in a blocked status and the occurrence and session (when present) are
in an accepting status. `capacity_blocking_statuses` determines which
registration statuses consume occurrence capacity.

Statuses are managed through `spatie/laravel-model-states`; transitions are
defined in each state base class's `config()` method:
`Features\Catalog\States\{EventStatus,OccurrenceStatus}`,
`Features\Registrations\States\RegistrationStatus`, and
`Features\Governance\States\EventModerationStatus`.

### Synchronization

```php
'sync' => [
    'classifications_to_facets' => env('EVENTS_SYNC_CLASSIFICATIONS_TO_FACETS', true),
    'audiences_to_facets' => env('EVENTS_SYNC_AUDIENCES_TO_FACETS', true),
    'build_search_documents' => env('EVENTS_SYNC_BUILD_SEARCH_DOCUMENTS', false),
],

'attribute_sync' => [
    'attribute_keys' => null,
    'audience_types' => null,
    'taxonomy_codes' => null,
],
```

These flags control the search document rebuild pipeline for events,
occurrences, and sessions.

- `classifications_to_facets` and `audiences_to_facets` add relation-backed facets to the search document payload.
- `build_search_documents` enables automatic search document creation and removal for events, occurrences, and sessions. When it is disabled, the Search feature observers short-circuit and no documents are written.
- `attribute_sync.attribute_keys`, `audience_types`, and `taxonomy_codes` let you narrow the synced records. Leave them `null` to sync everything.

### Resolvers (extensibility seams)

```php
'classifications' => ['resolver' => null],
'references' => ['resolver' => null],
'timezone' => ['display_timezone_resolver' => null],
'schedule' => ['resolver' => null],
'time_expressions' => ['resolver' => null],
'translations' => ['provider' => null],
'search' => [
    'payload_resolver' => null,
    'engine' => null,
    'indexer' => null,
    'relation_provider' => null,
    'queue_indexing' => env('EVENTS_SEARCH_QUEUE_INDEXING', false),
    'queue_connection' => env('EVENTS_SEARCH_QUEUE_CONNECTION'),
    'queue_name' => env('EVENTS_SEARCH_QUEUE_NAME'),
],
'change_notices' => [
    'audience_resolver' => null,
],
```

Each resolver can be bound to a custom class for domain-specific behavior.
The provider validates configured classes against the matching contract
(`EventClassificationResolver`, `EventReferenceResolver`,
`EventTranslationProvider`, `ResolvesEventTimeExpression`,
`EventScheduleResolver`, `EventDisplayTimezoneResolver`,
`EventSearchPayloadResolver`, `EventSearchRelationProvider`,
`EventSearchEngine`, `EventSearchIndexer`,
`EventChangeNoticeAudienceResolver`) and throws a `RuntimeException` when the
class does not implement it.

- `search.indexer` defaults to `AIArmada\Events\Features\Search\Actions\IndexEventSearchTargetAction`. Set it to `AIArmada\Events\Drivers\NullEventSearchIndexerDriver` to disable automatic indexing explicitly, or point it at a custom `EventSearchIndexer`.
- `search.relation_provider` defaults to `AIArmada\Events\Platform\DefaultEventSearchRelationProvider`, which returns only package-owned paths (`classifications.term` and `timeExpressions`). An application provider should compose that default and add its own relations.
- `timezone.display_timezone_resolver` defaults to `AIArmada\Events\Drivers\DefaultEventDisplayTimezoneDriver`; `schedule.resolver` defaults to the null driver.

### Integrations

```php
'integrations' => [
    'product_model' => class_exists(Product::class) ? Product::class : null,
    'variant_model' => class_exists(Variant::class) ? Variant::class : null,
    'customer_model' => class_exists(Customer::class) ? Customer::class : null,
    'order_model' => class_exists(Order::class) ? Order::class : null,
    'order_item_model' => class_exists(OrderItem::class) ? OrderItem::class : null,
    'inventory_location_model' => class_exists(InventoryLocation::class) ? InventoryLocation::class : null,
    'checkout_intent_resolver' => null,
    'order_item_fulfillment_resolver' => null,
]
```

The events package requires `aiarmada/addressing`. Venue, space, facility, and
event-location models use `HasAddresses` for canonical address attachments.
`Venue` and `EventLocation` read their canonical primary address attachment;
new writes must use `attachAddress()`. The default checkout-intent and
order-item-fulfillment resolvers bind automatically when the commerce
packages are present (see `Platform\CommerceIntegration`); custom resolvers
can override both.

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
]
```

The welcome notification is sent when a registration is approved.

## Drivers

External integrations resolve through `AIArmada\Events\Drivers` classes bound
to `Contracts` interfaces in the service provider. Rebind the contract in an
application provider to swap behavior; never import a driver class directly
from feature code:

| Contract | Default driver | Notes |
|---|---|---|
| `EventTicketingDriver` | `TicketingDriver` | Ticket types, pass issuance/revocation, inventory ships/restores |
| `EventSeatingDriver` | `SeatingDriver` | Seat holds, allocations, releases |
| `EventOrderDriver` | `OrdersDriver` | Order snapshots, refunds, item fulfilment markers |
| `EventCartDriver` | `CartCheckoutDriver` | Cart instances, ticket lines, checkout start |
| `EventAuthzDriver` | `AuthzDriver` | Management assignment mirroring |
| `EventChangeNoticeNotificationDispatcher` | `EventChangeNoticeNotificationDriver` | Sends through `aiarmada/communications` |
| `EventEngagementManager` | `NullEventEngagementDriver` (or engagement package) | Follow/bookmark/respond seam |
| `EventScheduleResolver` | `NullEventScheduleDriver` | Override via `events.schedule.resolver` |
| `EventSearchIndexer` | `IndexEventSearchTargetAction` (or `NullEventSearchIndexerDriver`) | Override via `events.search.indexer` |

## Change notices

`events.change_notices.audience_resolver` is the only change-notice config key.
Leave it `null` to use the package default
(`Features\Changes\Queries\DefaultEventChangeNoticeAudienceResolver`), or set
it to a class implementing
`AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver` (anything else
throws at boot).

Delivery is a container seam, not a config key: bind
`AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher` to change
delivery. The built-in `AIArmada\Events\Drivers\EventChangeNoticeNotificationDriver`
sends through `aiarmada/communications` and derives its audience scope from
the change log's `impact_level` (`critical`/`high` → `registrants`, otherwise
`followers`).

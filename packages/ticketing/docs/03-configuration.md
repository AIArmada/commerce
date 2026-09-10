---
title: Configuration
---

# Configuration

The ticketing package configuration is located in `config/ticketing.php`.

## Database Configuration

### Tables

```php
'database' => [
    'table_prefix' => 'ticket_',
    'tables' => [
        'ticket_types' => 'ticket_ticket_types',
        'ticket_type_components' => 'ticket_ticket_type_components',
        'ticket_type_products' => 'ticket_ticket_type_products',
        'ticket_type_seating_options' => 'ticket_ticket_type_seating_options',
        'passes' => 'ticket_passes',
        'pass_holders' => 'ticket_pass_holders',
        'pass_transfers' => 'ticket_pass_transfers',
    ],
],
```

Override any table name via environment variables:

```
TICKETING_TICKET_TYPES_TABLE=my_ticket_types
TICKETING_PASSES_TABLE=my_passes
TICKETING_TABLE_PREFIX=festival_
```

### JSON Column Type

Use the `commerce_json_column_type('ticketing', 'jsonb')` helper in migrations.

- Use `jsonb` for PostgreSQL (default) — better performance, supports indexing
- Use `json` for MySQL compatibility

## Defaults

```php
'defaults' => [
    'currency' => env('TICKETING_CURRENCY', env('COMMERCE_CURRENCY', 'MYR')),
    'pass_no_prefix' => env('TICKETING_PASS_NO_PREFIX', 'PASS-'),
],
```

| Key | Description |
|-----|-------------|
| `currency` | Default currency code for pricing (ISO 4217) |
| `pass_no_prefix` | Prefix for auto-generated pass numbers |

## Ticketable Types

The polymorphic ticketable registry is owned by the core package so it is
available to API, console, queue, and Filament consumers alike:

```php
'ticketable_types' => [
    App\\Models\\Workshop::class,
],
'allowed_ticketable_types' => [],
```

Register only classes implementing `TicketableInterface`. The optional
`allowed_ticketable_types` list narrows the configured and programmatic
registrations.

## Transfer Settings

```php
'transfers' => [
    'bulk_max_size' => env('TICKETING_BULK_TRANSFER_MAX', 100),
    'expiry_grace_period' => env('TICKETING_TRANSFER_EXPIRY_GRACE', 0),
],
```

| Key | Description |
|-----|-------------|
| `bulk_max_size` | Maximum passes per bulk transfer operation |
| `expiry_grace_period` | Additional seconds after transfer window closes during which transfers are still allowed |

## Notifications

```php
'notifications' => [
    'ticket' => [
        'enabled' => true,
        'from_address' => env('TICKETING_FROM_ADDRESS'),
        'from_name' => env('TICKETING_FROM_NAME'),
    ],
],
```

| Key | Description |
|-----|-------------|
| `enabled` | Enable or disable ticket-related notifications |
| `from_address` | Sender email address for ticket notifications |
| `from_name` | Sender name for ticket notifications |

## Features

```php
'features' => [
    'auto_issue_passes' => env('TICKETING_AUTO_ISSUE_PASSES', true),
],
'owner' => [
    'enabled' => env('TICKETING_OWNER_ENABLED', true),
    'include_global' => false,
    'auto_assign_on_create' => env('TICKETING_OWNER_AUTO_ASSIGN', true),
],
```

| Key | Description |
|-----|-------------|
| `auto_issue_passes` | Automatically issue passes when an order is paid (requires `aiarmada/orders`) |
| `owner.enabled` | Enforce the current `commerce-support` owner context on ticketing models |
| `owner.include_global` | Whether owner-scoped reads include global rows; ticketing keeps this disabled |
| `owner.auto_assign_on_create` | Assign the current owner to newly-created ticketing rows |

Ticket types, components, product and seating options, passes, pass holders, and
pass transfers are owner-scoped. Owner context is required for owner-enabled
reads and writes; global operations must be wrapped in an explicit
`OwnerContext::withOwner(null, ...)` scope. Existing rows with no owner are not
implicitly visible to tenants.

## Events

```php
'events' => [
    'pricing_consistency_check' => env('TICKETING_PRICING_CONSISTENCY_CHECK', true),
],
```

| Key | Description |
|-----|-------------|
| `pricing_consistency_check` | Validate pricing consistency between ticket type and order line items |

## Environment Variables

```bash
# .env
TICKETING_TABLE_PREFIX=ticket_
TICKETING_JSON_COLUMN_TYPE=jsonb
TICKETING_CURRENCY=MYR
TICKETING_PASS_NO_PREFIX=PASS-
TICKETING_BULK_TRANSFER_MAX=100
TICKETING_TRANSFER_EXPIRY_GRACE=0
TICKETING_FROM_ADDRESS=tickets@example.com
TICKETING_FROM_NAME=Ticketing
TICKETING_AUTO_ISSUE_PASSES=true
TICKETING_OWNER_ENABLED=true
TICKETING_OWNER_AUTO_ASSIGN=true
TICKETING_PRICING_CONSISTENCY_CHECK=true
```

## Accessing Configuration

```php
// Check if auto-issue is enabled
$autoIssue = config('ticketing.features.auto_issue_passes'); // true

// Get table name
$tableName = config('ticketing.database.tables.passes'); // 'ticket_passes'

// Get transfer max size
$maxSize = config('ticketing.transfers.bulk_max_size'); // 100
```

Models automatically resolve table names from config:

```php
use AIArmada\Ticketing\Models\Pass;

$table = (new Pass)->getTable(); // Uses config value
```

## Performance Optimization

### Database Indexes

The migrations include optimized indexes for common queries:

- `ticket_types`: `ticketable_type + ticketable_id`, `code`, sales window columns
- `passes`: `pass_no` (unique), `holder_email`, state columns, `ticket_type_id`
- `pass_transfers`: `pass_id`, `created_at`

### JSON Column Optimization

For PostgreSQL, `jsonb` is default. Add GIN indexes on metadata columns (manual migration):

```php
Schema::table('ticket_passes', function (Blueprint $table) {
    $table->index('metadata')->algorithm('gin');
});
```

## Security Considerations

### Owner Scoping

Owner context is provided by `commerce-support`. All ticketing models store the
owner tuple directly and enforce it through the shared owner scope. Related
ticketable, product, variant, section, holder, and registration models are
validated when they are owner-aware.

Cross-tenant access is not available through ordinary package queries. A system
operation must explicitly select the required owner context or use the shared
owner-scope opt-out in code that is intentionally cross-tenant.

## Read next

- [Usage](04-usage.md) — Learn how to use the package
- [Troubleshooting](99-troubleshooting.md) — Debug common issues

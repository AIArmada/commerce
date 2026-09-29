---
title: Usage Guide
---

# Usage Guide

## Managing Ticket Types

### Creating a Ticket Type

Navigate to **Ticketing > Ticket Types** and click **New Ticket Type**.

The form includes:

- **Name** — Display name for the ticket type
- **Code** — Unique short code (e.g., `GA`, `VIP`)
- **Ticketable Type** — Polymorphic type (select the model, e.g., Workshop)
- **Ticketable** — Specific record (select the workshop/course/event)
- **Price** — Ticket price in major units (e.g., `500.00` for RM500.00); stored as integer minor units
- **Currency** — ISO 4217 currency code (uppercased automatically, defaults to MYR)
- **Max Quantity** — Max per purchase (optional)
- **Admits Quantity** — Guests admitted per ticket (minimum 1)
- **Min Quantity** — Minimum per purchase (optional)
- **Sales Window** — Start and end dates for sales
- **Status / Visibility** — Lifecycle and access visibility

### Managing Components

The components relation manager lists linked child ticket types read-only:

- **Component** — Linked child ticket type name
- **Quantity** — Multiplier applied when expanding via `ExpandTicketTypeComponentsAction`

### Linking Bundle Products

The bundle products relation manager lists linked products read-only (requires `aiarmada/products`):

- **Product** — Linked product name
- **Quantity** — How many to auto-add to cart
- **Inclusion Mode** — Required or optional bundle behavior

### Managing ticket types on host resources

`AIArmada\FilamentTicketing\RelationManagers\TicketTypesRelationManager` manages ticket
types on any host resource whose model has a `ticketTypes` relationship. Register it
from the host resource's `getRelations()` (or through the host package's
relation-manager config seam, such as
`filament-events.resources.event_relation_managers`):

```php
use AIArmada\FilamentTicketing\RelationManagers\TicketTypesRelationManager;

public static function getRelations(): array
{
    return [TicketTypesRelationManager::class];
}
```

The form and table reuse the ticket-type resource definitions. The ticketable picker is
omitted (the owner record is the ticketable), and the code uniqueness rule scopes to
the owner automatically. Edit and delete actions revalidate the record against the
current owner scope.

## Viewing and Managing Passes

### Pass List

Navigate to **Ticketing > Passes**.

Columns include:
- **Pass No** — Unique pass identifier
- **Ticket Type** — Linked ticket type
- **Holder** — Name and email of the current holder
- **Status** — Current pass status (Issued, Activated, Used, etc.)
- **Issued At / Used At** — Lifecycle timestamps
- **Created** — When the pass record was created

Filters:
- **Status** — Filter by pass status

### Pass lifecycle actions

`PassResource` is read-only. It registers a single `ViewAction` and no state transition,
transfer, or delete actions, so pass state changes (activate, use, cancel, revoke, void,
expire) and transfers must go through the core `aiarmada/ticketing` services from your
own application code. Transfer authorization is enforced by your application before
invoking those services.

The pass view page shows one `Pass Details` section with the pass no, ticket type, holder
name and email, QR code, barcode, status, status reason, and the `issued_at`,
`activated_at`, `used_at`, `cancelled_at`, `revoked_at`, `voided_at`, `expired_at`, and
`transfer_expires_at` timestamps.

Use **Ticketing > Pass Transfers** for the read-only transfer audit log.

## Viewing Pass Holders

Navigate to **Ticketing > Pass Holders**. The resource registers a list page only, and
rows are searchable by name, email, and pass number:

- **Name** — Holder name
- **Email** — Holder email
- **Pass No.** — The related pass
- **Is Current** — Whether this holder record is the pass's current holder
- **Created** — When the holder record was created

## Viewing Transfer Log

Navigate to **Ticketing > Pass Transfers** to see the transfer audit log:

- **Pass No.** — Linked pass
- **From** — Previous holder
- **To** — New holder
- **Reason** — Transfer reason
- **Created** — Transfer timestamp

The resource registers a list page only and ships no filters, so the log is read-only
and unfiltered.

## Customizing Resources

### Extending TicketTypeResource

```php
use AIArmada\FilamentTicketing\Resources\TicketTypeResource as BaseResource;

class CustomTicketTypeResource extends BaseResource
{
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}
```

### Overriding in Panel

```php
use App\Filament\Resources\CustomTicketTypeResource;

public function panel(Panel $panel): Panel
{
    return $panel
        ->resources([
            CustomTicketTypeResource::class,
        ])
        ->plugins([
            FilamentTicketingPlugin::make(),
        ]);
}
```

## Registering Ticketable Types

```php
use AIArmada\Ticketing\Support\TicketableTypeRegistry;
use App\Models\CourseSession;

// In a service provider
public function boot(): void
{
    app(TicketableTypeRegistry::class)->register(CourseSession::class);
}
```

Or via config:

```php
// config/ticketing.php
'ticketable_types' => [
    \App\Models\CourseSession::class,
],
```

## Validation rules

The ticket type form enforces ticket-code uniqueness per ticketable, `admits_quantity` of at least 1, minimum quantity not exceeding maximum quantity, and sales end on or after sales start. Submitted ticketable references are revalidated server-side against the registered types and the current owner scope.

## Resource permissions

Each resource checks its own ability set (`ticket-type.*`, `pass.*`, `pass-holder.*`, `pass-transfer.*` with `viewAny`, `view`, `create`, `update`, `delete`). Panels must grant these permissions or non-super-admin users lose access.

## Read next

- [Configuration](03-configuration.md) — Review configuration options
- [Troubleshooting](99-troubleshooting.md) — Debug common issues

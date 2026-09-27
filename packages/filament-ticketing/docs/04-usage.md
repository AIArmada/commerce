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
- **Admits Quantity** — How many admissions this type grants (minimum 1)
- **Min/Max Quantity** — Min/max per purchase (optional)
- **Sales Window** — Start and end dates for sales

### Managing Pricing Components

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
- **Holder** — Name and email of the current holder
- **Ticket Type** — Linked ticket type
- **State** — Current state (Issued, Activated, Used, etc.)
- **Created** — When the pass was issued

Filters:
- **State** — Filter by pass status

### Pass State Transitions

The pass list and view pages are read-only (view action only). Run state transitions through the ticketing domain:

```php
$pass->markActivated();
$pass->save();

app(\AIArmada\Ticketing\Actions\RevokePassAction::class)->handle($pass, reason: 'Fraud detected');
```

Allowed transitions are defined in `AIArmada\Ticketing\States\PassState::config()`: `Pending` → `Issued`/`Expired`, `Issued`/`Activated` → `Activated`/`Used`/`Cancelled`/`Revoked`/`Expired`, `Used`/`Cancelled` → `Revoked`, `Revoked` → `Voided`; `Voided` and `Expired` are terminal.

### Pass Transfer

Passes are transferred through the ticketing domain, not a panel action:

```php
app(\AIArmada\Ticketing\Actions\TransferPassToHolderAction::class)->handle(
    pass: $pass,
    newHolder: $newHolder,
    reason: 'Gift',
);
```

Transfer authorization must be enforced by your application before invoking ticketing actions (pass `authorizedBy:` to enforce `PassTransferPolicy`).

### Viewing Transfer History

Use **Ticketing > Pass Transfers** for the transfer audit log showing all past transfers with:

- Previous holder
- New holder
- Reason
- Timestamp

## Viewing Pass Holders

Navigate to **Ticketing > Pass Holders** (read-only).

Search by name or email to find a holder and see:

- The linked pass number and whether the holder row is current (`is_current`)
- Holder type/id, transfer timestamp, and metadata on the detail view

## Viewing Transfer Log

Navigate to **Ticketing > Pass Transfers** to see the complete audit log:

- **Pass** — Linked pass (searchable by pass number)
- **From** — Previous holder
- **To** — New holder
- **Reason** — Transfer reason
- **Date** — Transfer timestamp (sortable)

The transfer log has no filters; search by pass number.

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

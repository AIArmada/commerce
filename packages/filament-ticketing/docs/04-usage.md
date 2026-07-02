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
- **Price** — Price in minor units (e.g., 50000 for RM500.00)
- **Currency** — ISO 4217 currency code
- **Max Quantity** — Max per purchase (optional)
- **Capacity** — Total capacity for this type (optional)
- **Sales Window** — Start and end dates for sales

### Managing Pricing Components

On the ticket type edit form, add pricing components to split the total price:

- **Name** — Component name (e.g., "Base Price", "Processing Fee")
- **Amount** — Component amount in minor units (must sum to total price)

### Linking Bundle Products

When `aiarmada/products` and `aiarmada/cart` are installed, you can link products:

- **Product** — Select a product from the dropdown
- **Quantity** — How many to auto-add to cart

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
- **Ticket Type** — Filter by ticket type
- **Holder Email** — Search by email

### Pass State Transitions

On the pass view page, available actions depend on the current state:

| Current State | Available Actions |
|---------------|-------------------|
| `Issued` | Activate, Cancel, Void |
| `Activated` | Use, Void |
| `Used` | *(no state transitions)* |
| `Cancelled` | *(no state transitions)* |
| `Revoked` | *(no state transitions)* |
| `Voided` | *(no state transitions)* |
| `Expired` | *(no state transitions)* |

Each action records the actor and reason.

### Pass Transfer

To transfer a pass from the admin panel:

1. Open the pass detail view
2. Click **Transfer**
3. Enter the new holder's name and email
4. Add a reason for the transfer
5. Submit

Transfer authorization must be enforced by your application before invoking ticketing actions.

### Viewing Transfer History

The pass detail page includes a **Transfer History** section showing all past transfers with:

- Previous holder
- New holder
- Reason
- Transferred by
- Timestamp

## Viewing Pass Holders

Navigate to **Ticketing > Pass Holders** (read-only).

Search by name or email to find a holder and see:

- All passes (current and past) associated with them
- Linked customer record (when `aiarmada/customers` is installed)
- Transfer history

## Viewing Transfer Log

Navigate to **Ticketing > Pass Transfers** to see the complete audit log:

- **Pass** — Linked pass
- **From** — Previous holder
- **To** — New holder
- **Reason** — Transfer reason
- **Authorized By** — Admin who authorized (if overridden)
- **Date** — Transfer timestamp

Filter transfers by date range, pass, or holder.

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
use AIArmada\FilamentTicketing\Support\TicketableTypeRegistry;
use App\Models\CourseSession;

// In a service provider
public function boot(): void
{
    app(TicketableTypeRegistry::class)->register(CourseSession::class);
}
```

Or via config:

```php
// config/filament-ticketing.php
'ticketable_types' => [
    \App\Models\CourseSession::class,
],
```

## Read next

- [Configuration](03-configuration.md) — Review configuration options
- [Troubleshooting](99-troubleshooting.md) — Debug common issues

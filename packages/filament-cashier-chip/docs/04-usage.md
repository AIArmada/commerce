---
title: Usage
---

# Usage

This guide covers the admin resources and portal-facing billing surfaces shipped by the plugin.

Filament Cashier CHIP provides three main resources for managing billing data.

## SubscriptionResource

Manage all subscriptions with full status tracking.

### Features

- List all subscriptions with status badges
- View subscription details and items
- Filter by status, trial state, cancellation, grace period, and past due
- Search by type, CHIP ID, price
- Global search enabled

### Table Columns

| Column | Description |
|--------|-------------|
| Type | Subscription type name |
| Customer | Owner/billable name |
| Status | Color-coded status badge |
| Price | Subscription price ID |
| Interval | Billing frequency |
| Trial Ends | Trial period end date |
| Next Billing | Next charge date |
| Created | Creation timestamp |

### Status Colors

Colors come from `FormatsSubscriptionStatus::getStatusColor()`:

| Status | Color | Description |
|--------|-------|-------------|
| Active | Success (green) | Subscription is active |
| Trialing | Warning (amber) | In trial period |
| Canceled | Danger (red) | Canceled |
| Past Due | Danger (red) | Payment failed |
| Incomplete | Warning (amber) | Initial payment pending |
| Unpaid | Danger (red) | Subscription unpaid |
| IncompleteExpired / anything else | Gray | — |

### Infolist Sections

The view page shows:

1. **Subscription Details** – Type, status, price, quantity
2. **Billing Information** – Interval, next billing date, recurring token
3. **Trial & Cancellation** – Trial end, grace period, cancellation dates
4. **Timestamps** – Created and updated dates

### Relation Manager

The `SubscriptionItemsRelationManager` displays subscription line items:

| Column | Description |
|--------|-------------|
| Price | Price identifier |
| Product | Product identifier |
| Quantity | Item quantity |
| Unit Amount | Price per unit |

### Customizing the Resource

`SubscriptionResource` is `final`, so extend `BaseCashierChipResource` and
register your own resource instead:

```php
namespace App\Filament\Resources;

use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use Filament\Support\Icons\Heroicon;

class SubscriptionResource extends BaseCashierChipResource
{
    protected static ?string $model = Subscription::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function getNavigationLabel(): string
    {
        return 'My Subscriptions';
    }

    protected static function navigationSortKey(): string
    {
        return 'subscriptions';
    }
}
```

## CustomerResource

View billable models and their CHIP client information.

### Features

- List all customers with CHIP IDs
- View customer billing details
- See associated subscriptions
- Filter by CHIP link, payment method, subscriptions, and trial state

### Table Columns

| Column | Description |
|--------|-------------|
| Name | Customer name |
| Email | Customer email |
| Chip ID | CHIP client ID |
| Linked | Whether the record is linked to CHIP |
| Payment Method | Default stored payment method |
| Subscriptions | Count of subscriptions |
| Joined | Account creation date |

### Infolist Sections

1. **Customer Information** – Name, email, phone
2. **CHIP Details** – Client ID, default payment method
3. **Subscriptions** – List of all subscriptions

### Customizing the Resource

`CustomerResource` is `final`. To change the billable model, swap it at
runtime instead of subclassing:

```php
use AIArmada\CashierChip\Billing\Cashier;

// e.g. in a service provider boot()
Cashier::useCustomerModel(App\Models\Team::class);
```

## InvoiceResource

Browse invoices from CHIP purchases.

### Features

- List all invoices/purchases
- View invoice details
- Filter by status
- Download invoice PDFs (if renderer configured)

### Table Columns

| Column | Description |
|--------|-------------|
| Number | Invoice/purchase ID |
| Customer | Customer name |
| Amount | Total amount |
| Status | Payment status |
| Date | Invoice date |

### Invoice Statuses

Statuses are CHIP purchase statuses, colored by
`AIArmada\Chip\Models\Purchase::statusColor()`:

| Status | Color | Description |
|--------|-------|-------------|
| `paid`, `cleared`, `settled` | Success | Payment completed |
| `hold`, `preauthorized`, `pending_execute`, `pending_charge`, `pending_capture`, `pending_release`, `pending_refund`, `overdue` | Warning | In flight / awaiting payment |
| `refunded` | Info | Refunded |
| `error`, `blocked`, `cancelled`, `released`, `expired`, `chargeback` | Danger | Failed or reversed |
| anything else | Secondary | Unknown |

## Owner Scoping

All resources automatically apply owner scoping when
`cashier-chip.features.owner.enabled` is true. Subscription and invoice queries
use owner-column constraints. Customer queries use the configured customer
resolver because billable models often carry no owner tuple:

- the model's own owner tuple when it defines one,
- otherwise owner IS the customer (same class and key): only that record,
- otherwise an owned CHIP customer link or an owned subscription,
- otherwise no rows (fail closed).

Explicit global context sees global-only rows on tuple models and all rows on
billables without an owner tuple. Record actions on the customer view page
revalidate the record through the same resolver and throw on cross-tenant
access. Point `cashier-chip.features.owner.customer_resolver` at a custom
`AIArmada\CashierChip\Contracts\CustomerOwnerResolverInterface` implementation
when the default mapping does not fit.

This ensures:
- Each tenant only sees their own data
- Cross-tenant data is never exposed
- Works with Filament's tenant features

### Disabling Owner Scoping

For super-admin panels that need global access:

```php
// config/cashier-chip.php
'features' => [
    'owner' => [
        'enabled' => false, // Disable for this panel
    ],
],
```

## Resource Actions

### Subscription Actions

The list is view-only at the row level (`ViewAction` only). Row and bulk
actions are configured through the table, not a `getTableActions()` hook —
that v3-era method no longer exists in Filament v5:

```php
use Filament\Actions\Action;

// inside a Table::configure() chain
->actions([
    Action::make('cancel')
        ->icon('heroicon-o-x-mark')
        ->color('danger')
        ->requiresConfirmation()
        ->action(fn (Subscription $record) => $record->cancel()),
])
->bulkActions([
    Action::make('resume')
        ->requiresConfirmation()
        ->action(function (Collection $records): void {
            $records->each(fn (Subscription $record) => $record->unpause());
        }),
])
```

## Global Search

Subscriptions are globally searchable by:
- Type name
- CHIP ID
- Price identifier

Enable in your panel:

```php
$panel->globalSearch(true);
```

## Navigation Badges

Each resource shows an owner-scoped count badge, cached briefly
(see `navigation.badge_cache_ttl`). The badge is hidden when no owner
context can be resolved.

## Bulk Lifecycle Actions

The subscription list offers Bulk Pause and Bulk Resume header actions.
Both iterate the owner-scoped resource query in chunks and call the
domain transitions (`pause()` / `unpause()`) per subscription, so
`paused_at` timestamps, model events, and owner isolation are preserved.
Per-row failures are reported and counted instead of aborting the run.

## Customer Sync

The customer list offers a Sync All to Chip header action. It dispatches
the `SyncCustomersToChipJob` queued job, which syncs unlinked customers
in chunks within the dispatching owner context. Progress and failures
are written to the application log.

## Next Steps

- [Billing Portal](05-billing-portal.md) – Customer self-service
- [Widgets](06-widgets.md) – Dashboard analytics

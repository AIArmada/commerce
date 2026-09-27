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
- Filter by status, trial, canceled, grace period, past due
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

| Status | Color | Description |
|--------|-------|-------------|
| Active | Success (green) | Subscription is active |
| Trialing | Warning (amber) | In trial period |
| Past Due | Danger (red) | Payment failed |
| Canceled | Danger (red) | Canceled but in grace period |
| Incomplete | Warning (amber) | Initial payment pending |
| Paused | Gray | Temporarily paused |

### Infolist Sections

The view page shows:

1. **Subscription Overview** – Type, CHIP ID, status, quantity
2. **Plan Details** – Price, billing interval, recurring token
3. **Customer** – Owning billable model
4. **Billing Schedule** – Trial end, next billing date, grace period, cancellation dates
5. **Discount** – Applied coupon details
6. **Timestamps** – Created and updated dates

### Relation Manager

The `SubscriptionItemsRelationManager` displays subscription line items:

| Column | Description |
|--------|-------------|
| Price | Price identifier |
| Product | Product identifier |
| Quantity | Item quantity |
| Unit Amount | Price per unit |

### Customizing the Resource

Package resources are `final`, so build your own resource and reuse the
package's table and infolist configurators:

```php
namespace App\Filament\Resources;

use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Tables\SubscriptionTable;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class CustomSubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function getNavigationLabel(): string
    {
        return 'My Subscriptions';
    }

    public static function table(Table $table): Table
    {
        return SubscriptionTable::configure($table);
    }
}
```

## CustomerResource

View billable models and their CHIP client information.

### Features

- List all customers with CHIP IDs
- View customer billing details
- See associated subscriptions
- Filter by CHIP customer status

### Table Columns

| Column | Description |
|--------|-------------|
| Name | Customer name |
| Email | Customer email |
| CHIP ID | CHIP client ID |
| Subscriptions | Count of active subscriptions |
| Created | Account creation date |

### Infolist Sections

1. **Customer Details** – Name, email, phone
2. **Billing Information** – CHIP client ID, default payment method
3. **Subscription Status** – Trial state and subscription counts
4. **Account Information** – Created and updated dates

### Customizing the Resource

The customer resource resolves its model from `Cashier::$customerModel`.
To use a custom billable model, register it in a service provider:

```php
use AIArmada\CashierChip\Billing\Cashier;

// In AppServiceProvider::boot()
Cashier::useCustomerModel(\App\Models\Team::class);
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

Invoices are CHIP purchases, so the Status badge shows the purchase status
with CHIP status colors, alongside a separate Paid icon:

| Status | Color | Description |
|--------|-------|-------------|
| Paid / Cleared / Settled | Success | Payment completed |
| Hold / Preauthorized / Pending * | Warning | Awaiting completion |
| Refunded | Info | Payment refunded |
| Error / Cancelled / Expired / … | Danger | Failed or ended |

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

The package table is view-only. Since the resource is `final`, add custom
actions in your own resource's `table()` definition:

```php
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Tables\SubscriptionTable;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

public static function table(Table $table): Table
{
    $table = SubscriptionTable::configure($table);

    return $table->actions([
        ...$table->getRecordActions(),
        Action::make('cancel')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn ($record) => $record->cancel()),

        Action::make('resume')
            ->icon('heroicon-o-play')
            ->color('success')
            ->visible(fn ($record) => $record->onGracePeriod())
            ->action(fn ($record) => $record->resume()),
    ]);
}
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

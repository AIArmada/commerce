---
title: Usage
---

# Usage

This guide covers the registered resources and the optional operational surfaces exposed by the plugin.

All resources extend `BaseChipResource` which provides owner scoping, consistent navigation, and shared table/form components.

## Registered Resources

These resources are registered automatically by the default plugin setup:

| Resource | Model | Description |
|----------|-------|-------------|
| `PurchaseResource` | `Purchase` | Payment transactions |
| `ClientResource` | `Client` | Customer records |
| `PaymentResource` | `Payment` | Payment records |
| `SendInstructionResource` | `SendInstruction` | Payout instructions |
| `BankAccountResource` | `BankAccount` | Payout bank accounts |

Turn the whole operator set off (or back on) with:

```php
FilamentChipPlugin::make()->operatorResources(false);
```

## Optional Resources

This resource exists but is not registered by default:

| Resource | Model | Description |
|----------|-------|-------------|
| `CompanyStatementResource` | `CompanyStatement` | Company statements |

### Registering the Optional Resource

```php
// In your PanelProvider
use AIArmada\FilamentChip\FilamentChipPlugin;

$panel->plugin(FilamentChipPlugin::make()->developerResources());
```

Or register the class directly:

```php
$panel->resources([
    AIArmada\FilamentChip\Resources\CompanyStatementResource::class,
]);
```

## PurchaseResource

The primary resource for viewing payment transactions.

### Table Columns

| Column | Description |
|--------|-------------|
| Reference | CHIP purchase reference |
| Client Email | Customer email |
| Invoice Reference | `purchase.reference` (hidden by default) |
| Grand Total | Formatted purchase total in minor units |
| Discount override | `purchase.total_discount_override` |
| Tax override | `purchase.total_tax_override` |
| Fee | `payment.fee_amount` |
| Status | Payment status badge |
| Created | `created_on` |
| Due | `due` |
| Test Mode | `is_test` |

### Filters

- **Status** - `SelectFilter` over `PurchaseStatus` cases
- **Test Mode** - toggle for `is_test`
- **High Value (≥ 5,000)** - JSON total filter

### Actions

- **View** - Opens the purchase infolist page

The list ships no refund or cancel actions; those belong to `aiarmada/chip`.

## ClientResource

Customer records synchronized from CHIP.

### Features

- List and view client details (name, contact, company, and tax identifiers)
- Filter by country, phone presence, shipping address, and company details
- Global search over email, name, phone, legal name, brand name, registration
  number, and tax number

Actions are view-only.

## BankAccountResource

Manage payout recipient bank accounts.

### Status Badges

- `pending` - Yellow, awaiting verification
- `verified` - Green, ready for payouts
- `rejected` - Red, verification failed

## SendInstructionResource

Manage disbursements and payouts.

### Status States

| Status | Description |
|--------|-------------|
| received | Received |
| enquiring | Verifying |
| executing | Processing |
| reviewing | In Review |
| accepted | Accepted |
| completed | Completed |
| rejected | Rejected |

## Extending Resources

Every shipped resource is `final`, so extend `BaseChipResource` and register
your own resource. `getTableColumns()` is a v3-era hook that no longer exists in
Filament v5 — build the column list explicitly:

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentChip\Resources\BaseChipResource;
use AIArmada\Chip\Models\Purchase;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseResource extends BaseChipResource
{
    protected static ?string $model = Purchase::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->label('Reference'),
            TextColumn::make('status')->badge(),
            TextColumn::make('custom_field'),
        ]);
    }

    protected static function navigationSortKey(): string
    {
        return 'purchases';
    }
}
```

## Owner Scoping

`BaseChipResource::getEloquentQuery()` calls the model's `scopeForOwner()`
(applied automatically by `HasOwner`), or fails closed with `1 = 0` when the
model cannot be owner-scoped. Owner scoping itself is enabled in
`config/chip.php` via `chip.owner.enabled`.

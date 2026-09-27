---
title: Usage
---

# Usage

This guide covers the registered resources and the optional operational surfaces exposed by the plugin.

All resources extend `BaseChipResource` which provides owner scoping, consistent navigation, and shared table/form components.

## Registered Resources

These resources are registered automatically (operator resources):

| Resource | Model | Description |
|----------|-------|-------------|
| `PurchaseResource` | `Purchase` | Payment transactions |
| `ClientResource` | `Client` | Customer records |
| `PaymentResource` | `Payment` | Payment records |
| `SendInstructionResource` | `SendInstruction` | Payout instructions |
| `BankAccountResource` | `BankAccount` | Payout bank accounts |

## Optional Resources

This resource exists but is not registered by default:

| Resource | Model | Description |
|----------|-------|-------------|
| `CompanyStatementResource` | `CompanyStatement` | Company statements |

### Registering Optional Resources

```php
// In your PanelProvider
use AIArmada\FilamentChip\FilamentChipPlugin;

$panel->plugin(
    FilamentChipPlugin::make()->developerResources()
);
```

## PurchaseResource

The primary resource for viewing payment transactions.

### Table Columns

| Column | Description |
|--------|-------------|
| Reference | CHIP reference |
| Client Email | Customer email |
| Grand Total | Transaction amount |
| Status | Payment status |
| Created | Creation timestamp |
| Due | Due timestamp |
| Test Mode | Whether this is a test purchase |

### Filters

- **Status** - Filter by purchase status
- **Test Mode** - Show test purchases only
- **High Value** - Purchases at or above 5,000

### Actions

- **View** - Page with full purchase details

## ClientResource

Customer records synchronized from CHIP.

### Features

- List client records
- View client details

## BankAccountResource

Manage payout recipient bank accounts (optional).

### Status Badges

- `pending` - Yellow, awaiting verification
- `verified` - Green, ready for payouts
- `rejected` - Red, verification failed

## SendInstructionResource

Manage disbursements and payouts (optional).

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
| deleted | Deleted |

## Extending Resources

Package resources are `final`, so build your own resource and reuse the
package's table and infolist configurators:

### Custom Table

```php
<?php

namespace App\Filament\Resources;

use AIArmada\Chip\Models\Purchase;
use AIArmada\FilamentChip\Resources\PurchaseResource\Tables\PurchaseTable;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomPurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    public static function table(Table $table): Table
    {
        $table = PurchaseTable::configure($table);

        return $table->columns([
            ...$table->getColumns(),
            TextColumn::make('custom_field'),
        ]);
    }
}
```

## Owner Scoping

All resources respect owner scoping from `commerce-support`.
`BaseChipResource::getEloquentQuery()` applies the model's `scopeForOwner()`
automatically and fails closed when the model has no owner scope. To add
your own constraints on top:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('status', 'paid');
}
```

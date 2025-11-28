# Resources

Resources for viewing CHIP data in your Filament admin panel. All resources use models from the `aiarmada/chip` package.

## Available Resources

| Resource | Model | Description |
|----------|-------|-------------|
| PurchaseResource | `AIArmada\Chip\Models\Purchase` | Payment transactions |
| PaymentResource | `AIArmada\Chip\Models\Payment` | Invoice payments |
| ClientResource | `AIArmada\Chip\Models\Client` | Customer records |

## Purchase Resource

Displays one-time and recurring payment transactions.

### Table Columns
- External ID, Amount, Currency, Status
- Customer email, timestamps

### Actions
- View modal with full transaction details
- Filter by status (pending, paid, failed)

## Payment Resource

Invoice-based payments with delivery information.

### Table Columns
- ID, Invoice ID, Amount, Status
- Delivery details

## Client Resource

Customer records synced from CHIP.

### Table Columns
- Name, Email, Phone
- Created date

## Extending Resources

Override the base resource to customize:

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentChip\Resources\PurchaseResource as BasePurchaseResource;

class PurchaseResource extends BasePurchaseResource
{
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                // your custom columns
            ]);
    }
}
```

Register in your panel:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FilamentChipPlugin::make()
                ->resources([
                    PurchaseResource::class,
                ])
        );
}
```

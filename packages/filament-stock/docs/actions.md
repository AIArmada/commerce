# Actions

The Filament Stock plugin provides reusable actions for stock management.

## QuickAddStockAction

A header action for quickly adding stock without navigating to the create page.

### Usage

```php
use AIArmada\FilamentStock\Actions\QuickAddStockAction;

protected function getHeaderActions(): array
{
    return [
        QuickAddStockAction::make(),
    ];
}
```

### Form Fields

| Field | Type | Description |
|-------|------|-------------|
| Stockable Type | Select | Choose from registered stockable types |
| Stockable ID | Text | Enter the model's primary key |
| Quantity | Number | Amount to add (1-99999) |
| Reason | Select | restock, return, or adjustment |
| Note | Textarea | Optional transaction note |

### Customization

```php
QuickAddStockAction::make()
    ->label('Quick Restock')
    ->color('success')
    ->icon('heroicon-o-plus');
```

### Behavior

- Defaults to "restock" reason
- Validates quantity between 1 and 99,999
- Creates a stock transaction with type "in"
- Shows success notification with stockable ID

## ExtendReservationAction

Extend a stock reservation's expiry time.

### Usage

```php
use AIArmada\FilamentStock\Actions\ExtendReservationAction;

// In a ViewRecord page
protected function getHeaderActions(): array
{
    return [
        ExtendReservationAction::make(),
    ];
}
```

### Form Fields

| Field | Type | Description |
|-------|------|-------------|
| Extension Time (minutes) | Number | Minutes to add to current expiry |

### Customization

```php
ExtendReservationAction::make()
    ->label('Add Time')
    ->color('warning')
    ->defaultMinutes(60); // Set custom default
```

### Behavior

- Only visible for non-expired reservations (`isValid()`)
- Default extension time from config (`default_extension_minutes`)
- Updates the reservation's `expires_at` field
- Shows new expiry time in success notification

### Conditional Visibility

The action automatically hides when:
- The record is not a `StockReservation`
- The reservation has already expired

## ReleaseReservationAction

Release a stock reservation and free the reserved quantity.

### Usage

```php
use AIArmada\FilamentStock\Actions\ReleaseReservationAction;

// In a ViewRecord page
protected function getHeaderActions(): array
{
    return [
        ReleaseReservationAction::make(),
    ];
}
```

### Customization

```php
ReleaseReservationAction::make()
    ->label('Cancel Reservation')
    ->icon('heroicon-o-x-circle');
```

### Behavior

- Shows confirmation modal before releasing
- Deletes the reservation record
- Redirects to reservation list after release
- Shows success notification

## Combining Actions

Use all reservation actions together:

```php
protected function getHeaderActions(): array
{
    return [
        ExtendReservationAction::make(),
        ReleaseReservationAction::make(),
    ];
}
```

## Creating Custom Actions

Extend the base Filament Action class:

```php
use Filament\Actions\Action;
use AIArmada\Stock\Models\StockTransaction;

class MyStockAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'my-stock-action';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('My Action')
            ->icon('heroicon-o-cube')
            ->action(function (array $data) {
                // Your logic here
            });
    }
}
```

## Table Actions

For table row actions on resources:

```php
use Filament\Tables\Actions\Action;

Tables\Actions\Action::make('adjust')
    ->label('Adjust Stock')
    ->icon('heroicon-o-adjustments-horizontal')
    ->form([
        Forms\Components\TextInput::make('adjustment')
            ->numeric()
            ->required(),
    ])
    ->action(function (Model $record, array $data) {
        $record->addStock($data['adjustment'], 'adjustment');
    });
```

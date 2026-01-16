---
title: Pages & Widgets
---

# Pages & Widgets

## ManagePricingSettings Page

Settings page for configuring pricing defaults.

### Location

```
Settings > Pricing Settings
```

### Navigation

```php
protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
protected static string|UnitEnum|null $navigationGroup = 'Settings';
protected static ?int $navigationSort = 10;
```

### View

Uses a simple form view:

```blade
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
    </x-filament-panels::form>
</x-filament-panels::page>
```

### Form Components

| Component | Type | Description |
|-----------|------|-------------|
| defaultCurrency | Select | Currency dropdown (8 options) |
| decimalPlaces | TextInput | Numeric, 0-4 range |
| roundingMode | Select | up, down, half_up, half_down |
| pricesIncludeTax | Toggle | Tax inclusion flag |
| minimumOrderValue | TextInput | Numeric, cents suffix |
| maximumOrderValue | TextInput | Numeric, cents suffix |
| promotionalPricingEnabled | Toggle | Feature flag |
| tieredPricingEnabled | Toggle | Feature flag |
| customerGroupPricingEnabled | Toggle | Feature flag |

### Data Persistence

Settings are persisted using Spatie Laravel Settings:

```php
public function save(): void
{
    $settings = app(PricingSettings::class);

    $settings->defaultCurrency = $state['defaultCurrency'];
    $settings->decimalPlaces = $state['decimalPlaces'];
    // ... other fields

    $settings->save();

    Notification::make()
        ->title(__('Saved'))
        ->success()
        ->send();
}
```

### Header Actions

```php
protected function getHeaderActions(): array
{
    return [
        \Filament\Actions\Action::make('save')
            ->label(__('Save'))
            ->icon('heroicon-o-check')
            ->color('primary')
            ->action('save'),
    ];
}
```

---

## PriceSimulator Page

Interactive price calculation testing tool.

### Requirements

- `aiarmada/products` package for Product/Variant models
- Optional: `aiarmada/customers` for customer selection

### Location

```
Pricing > Price Simulator
```

### Navigation

```php
protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
protected static string|UnitEnum|null $navigationGroup = 'Pricing';
protected static ?int $navigationSort = 99;
protected static ?string $title = 'Price Simulator';
```

### Form Schema

**Input Parameters Section**:

| Field | Type | Description |
|-------|------|-------------|
| product_type | Select | Product or Variant |
| product_id | Select | Searchable, shows base price |
| variant_id | Select | Searchable, shows product + SKU |
| customer_id | Select | Optional, searchable |
| quantity | TextInput | Numeric, min 1 |
| effective_date | DateTimePicker | For future pricing |

### Calculate Action

```php
public function calculate(): void
{
    // Get priceable based on type
    $priceable = $data['product_type'] === 'product'
        ? Product::find($data['product_id'])
        : Variant::find($data['variant_id']);

    // Build context
    $context = [];
    if ($customer) {
        $context['customer_id'] = $customer->id;
    }
    if ($effectiveAt) {
        $context['effective_at'] = $effectiveAt;
    }

    // Calculate
    $calculator = app(PriceCalculatorInterface::class);
    $result = $calculator->calculate($priceable, $quantity, $context);

    // Store result for display
    $this->result = [
        'original_price' => $result->originalPrice,
        'final_price' => $result->finalPrice,
        // ... other fields
    ];
}
```

### Result Infolist

Displays calculation results using Filament Infolist components:

**Price Calculation Result**:
- Grid of original, final, and discount prices
- Quantity and total price

**Applied Pricing Rules**:
- Price list name (badge)
- Promotion name (badge)
- Tier description (badge)
- Discount percentage

**Breakdown**:
- Repeatable entries showing each calculation step

### View Template

```blade
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="calculate">
        {{ $this->form }}
    </x-filament-panels::form>

    @if ($result)
        <div class="mt-6">
            {{ $this->resultInfolist }}
        </div>
    @else
        <div class="mt-6">
            <!-- Ready state with calculator icon -->
        </div>
    @endif
</x-filament-panels::page>
```

### Header Actions

```php
protected function getHeaderActions(): array
{
    return [
        Action::make('calculate')
            ->label('Calculate Price')
            ->icon('heroicon-o-calculator')
            ->color('primary')
            ->action('calculate'),
            
        Action::make('clear')
            ->label('Clear')
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->action('clear')
            ->visible(fn () => $this->result !== null),
    ];
}
```

---

## PricingStatsWidget

Dashboard statistics widget.

### Configuration

```php
protected ?string $pollingInterval = '30s';
```

### Statistics

The widget displays owner-scoped statistics:

```php
protected function getStats(): array
{
    // Active price lists
    $activePriceLists = PricingOwnerScope::applyToOwnedQuery(
        PriceList::query()
    )->active()->count();

    $stats = [
        Stat::make('Active Price Lists', number_format($activePriceLists))
            ->description('Currently active')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color('info'),
    ];

    // Promotion stats (if package installed)
    if (class_exists(Promotion::class)) {
        $promotionQuery = Promotion::query();

        if (PromotionsOwnerScope::isEnabled()) {
            $promotionQuery = $promotionQuery->forOwner();
        }

        $activePromotions = (clone $promotionQuery)->active()->count();
        $totalPromotionUsage = $promotionQuery->sum('usage_count');

        $stats[] = Stat::make('Active Promotions', number_format($activePromotions))
            ->description('Running promotions')
            ->descriptionIcon('heroicon-m-gift')
            ->color('success');

        $stats[] = Stat::make('Promotion Uses', number_format($totalPromotionUsage))
            ->description('Total redemptions')
            ->descriptionIcon('heroicon-m-receipt-percent')
            ->color('warning');
    }

    return $stats;
}
```

### Displayed Stats

| Stat | Icon | Color | Description |
|------|------|-------|-------------|
| Active Price Lists | currency-dollar | info | Count of active lists |
| Active Promotions | gift | success | Running promotions (if installed) |
| Promotion Uses | receipt-percent | warning | Total redemptions (if installed) |

### Customization

To customize the widget, extend it:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentPricing\Widgets\PricingStatsWidget as BaseWidget;

class CustomPricingStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $stats = parent::getStats();

        // Add custom stats
        $stats[] = Stat::make('Custom Stat', '100')
            ->description('Custom description');

        return $stats;
    }
}
```

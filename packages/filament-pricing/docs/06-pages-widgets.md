---
title: Pages & Widgets
---

# Pages & Widgets

## ManagePricingSettings Page

Settings page for configuring pricing defaults. Pricing settings are a single global row shared by every tenant, so the page requires the `filament-pricing.authorization.settings_ability` ability (default `pricing.manage-settings`, defined via a `Gate` in the host app). Saves are validated server-side against the form rules.

> **warning**
> Any ability holder changes pricing defaults for all tenants. Keep the ability narrowly assigned.

### Location

```
Settings > Pricing Settings
```

### Navigation

```php
protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

public static function getNavigationGroup(): string|UnitEnum|null
{
    return config('filament-pricing.navigation.settings_group');
}

public static function getNavigationSort(): ?int
{
    $sort = config('filament-pricing.pages.navigation_sort.settings');

    return is_numeric($sort) ? (int) $sort : null;
}
```

### View

Uses a simple form view:

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
    </form>
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

- `aiarmada/products` is optional. When it is unavailable, the page renders a disabled state instead of attempting product or variant queries.
- Optional: `aiarmada/customers` for customer selection

### Location

```
Pricing > Price Simulator
```

### Navigation

```php
protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
protected static ?string $title = 'Price Simulator';

public static function getNavigationGroup(): string|UnitEnum|null
{
    return config('filament-pricing.navigation.group');
}

public static function getNavigationSort(): ?int
{
    $sort = config('filament-pricing.pages.navigation_sort.price_simulator');

    return is_numeric($sort) ? (int) $sort : null;
}
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
    // Input is re-validated server-side (bypassable through direct Livewire calls)
    $input = self::validatedSimulationInput($this->data ?? []);

    if ($input === null) {
        $this->result = null;

        return;
    }

    $owner = OwnerContext::resolve();
    $includeGlobal = (bool) config('products.features.owner.include_global', false);

    // Get priceable based on type — always owner-scoped
    $priceable = $input['product_type'] === 'product'
        ? OwnerQuery::applyToEloquentBuilder(Product::query(), $owner, $includeGlobal)->find($input['id'])
        : Variant::query()
            ->whereHas('product', fn ($q) => OwnerQuery::applyToEloquentBuilder($q, $owner, $includeGlobal))
            ->find($input['id']);

    if (! $priceable) {
        $this->result = null;

        return;
    }

    // Build context
    $context = [];

    if ($customer) {
        $context['customer_id'] = (string) $customer->getKey();
    }

    if ($effectiveAt instanceof DateTimeInterface) {
        $context['effective_at'] = $effectiveAt;
    }

    // Calculate — named arguments, item must implement Priceable
    $calculator = app(PriceCalculatorInterface::class);
    $result = $calculator->calculate(
        item: $priceable,
        quantity: $input['quantity'],
        context: $context,
    );

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
    <form wire:submit="calculate">
        {{ $this->form }}
    </form>

    @if ($result)
        <div class="mt-6">
            {{ $this->resultInfolist }}
        </div>
    @else
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">Ready to Calculate</x-slot>
                <!-- inline calculator icon + hint copy -->
            </x-filament::section>
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
use AIArmada\CommerceSupport\Support\OwnerContext;

protected function getStats(): array
{
    // Active price lists
    $activePriceLists = PriceList::query()->active()->count();

    $stats = [
        Stat::make('Active Price Lists', number_format($activePriceLists))
            ->description('Currently active')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color('info'),
    ];

    // Promotion stats (if package installed)
    if (class_exists(Promotion::class)) {
        $promotionQuery = Promotion::query();

        if ((bool) config('promotions.features.owner.enabled', false)) {
            $promotionQuery = $promotionQuery->forOwner(
                OwnerContext::resolve(),
                (bool) config('promotions.features.owner.include_global', false),
            );
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

> **warning**
> `PricingStatsWidget` is `final`. `class CustomPricingStatsWidget extends PricingStatsWidget` is a fatal error.

Write your own `StatsOverviewWidget` and register it on the panel instead:

```php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomPricingStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        return [
            Stat::make('Active Price Lists', number_format(\AIArmada\Pricing\Models\PriceList::query()->active()->count()))
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),
        ];
    }
}
```

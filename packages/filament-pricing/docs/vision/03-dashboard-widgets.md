# Dashboard Widgets

> **Document:** 03 of 03  
> **Package:** `aiarmada/filament-pricing`  
> **Status:** Vision

---

## Active Promotions Widget

```php
namespace AIArmada\FilamentPricing\Widgets;

class ActivePromotionsWidget extends TableWidget
{
    protected static ?string $heading = 'Active Promotions';

    protected function getTableQuery(): Builder
    {
        return PriceRule::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now())
            )
            ->where(fn ($q) => $q
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now())
            )
            ->orderBy('priority', 'desc');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name'),
            TextColumn::make('action_summary')
                ->getStateUsing(fn ($record) => $this->formatAction($record)),
            TextColumn::make('ends_at')
                ->since()
                ->placeholder('No end date'),
            TextColumn::make('usage_count')
                ->label('Uses'),
        ];
    }
}
```

---

## Upcoming Promotions Widget

```php
class UpcomingPromotionsWidget extends TableWidget
{
    protected static ?string $heading = 'Upcoming Promotions';

    protected function getTableQuery(): Builder
    {
        return PriceRule::query()
            ->where('is_active', true)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name'),
            TextColumn::make('starts_at')
                ->dateTime()
                ->description(fn ($record) => $record->starts_at->diffForHumans()),
            TextColumn::make('ends_at')->dateTime(),
        ];
    }
}
```

---

## Price List Stats Widget

```php
class PriceListStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Price Lists', PriceList::where('is_active', true)->count()),
            Stat::make('Active Rules', PriceRule::where('is_active', true)->count()),
            Stat::make('Products with Custom Pricing', 
                Price::distinct('priceable_id')->count()
            ),
            Stat::make('Tiered Products', 
                PriceTier::distinct('priceable_id')->count()
            ),
        ];
    }
}
```

---

## Price Simulator Widget

```php
class PriceSimulatorWidget extends Widget
{
    protected static string $view = 'filament-pricing::widgets.price-simulator';

    public ?int $productId = null;
    public ?int $customerId = null;
    public int $quantity = 1;
    public ?array $result = null;

    public function calculate(): void
    {
        $product = Product::findOrFail($this->productId);
        $customer = $this->customerId ? Customer::find($this->customerId) : null;

        $resolver = app(PriceResolver::class);
        $this->result = $resolver->resolve($product, $customer, $this->quantity)->toArray();
    }
}
```

Simulator View:
```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Price Simulator</x-slot>
        
        <div class="space-y-4">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="productId">
                    <option value="">Select Product</option>
                    @foreach(Product::all() as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="customerId">
                    <option value="">Guest Customer</option>
                    @foreach(Customer::limit(50)->get() as $c)
                        <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input type="number" wire:model="quantity" min="1" />
            </x-filament::input.wrapper>

            <x-filament::button wire:click="calculate">
                Calculate Price
            </x-filament::button>

            @if($result)
                <div class="p-4 bg-gray-50 rounded-lg">
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt>Base Price</dt>
                            <dd>{{ money($result['base_price']) }}</dd>
                        </div>
                        @if($result['price_list'])
                        <div class="flex justify-between text-green-600">
                            <dt>Price List: {{ $result['price_list']['name'] }}</dt>
                            <dd>{{ money($result['list_price']) }}</dd>
                        </div>
                        @endif
                        <div class="flex justify-between font-bold border-t pt-2">
                            <dt>Final Price</dt>
                            <dd>{{ money($result['final_price']) }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## Navigation

**Previous:** [02-price-list-resource.md](02-price-list-resource.md)  
**Back to:** [01-executive-summary.md](01-executive-summary.md)

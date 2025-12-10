# Dashboard Widgets

> **Document:** 03 of 03  
> **Package:** `aiarmada/filament-tax`  
> **Status:** Vision

---

## Tax Configuration Stats Widget

```php
namespace AIArmada\FilamentTax\Widgets;

class TaxStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Tax Zones', TaxZone::count()),
            Stat::make('Tax Classes', TaxClass::count()),
            Stat::make('Tax Rates', TaxRate::count()),
            Stat::make('Active Exemptions', TaxExemption::where('is_verified', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->count()),
        ];
    }
}
```

---

## Expiring Exemptions Widget

```php
class ExpiringExemptionsWidget extends TableWidget
{
    protected static ?string $heading = 'Expiring Exemptions (30 Days)';

    protected function getTableQuery(): Builder
    {
        return TaxExemption::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>=', now())
            ->with(['customer', 'taxZone'])
            ->orderBy('expires_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('customer.full_name'),
            TextColumn::make('certificate_number'),
            TextColumn::make('taxZone.name')->placeholder('All'),
            TextColumn::make('expires_at')
                ->date()
                ->description(fn ($record) => $record->expires_at->diffForHumans()),
        ];
    }
}
```

---

## Tax by Zone Chart Widget

```php
class TaxByZoneWidget extends ChartWidget
{
    protected static ?string $heading = 'Tax Collected by Zone';

    protected function getData(): array
    {
        // This would come from order tax records
        $data = DB::table('order_tax_lines')
            ->join('tax_zones', 'order_tax_lines.tax_zone_id', '=', 'tax_zones.id')
            ->selectRaw('tax_zones.name, SUM(order_tax_lines.amount) as total')
            ->where('order_tax_lines.created_at', '>=', now()->startOfMonth())
            ->groupBy('tax_zones.name')
            ->pluck('total', 'name');

        return [
            'datasets' => [[
                'data' => $data->values()->map(fn ($v) => $v / 100)->toArray(),
                'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
            ]],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
```

---

## Zone Coverage Map Widget

```php
class ZoneCoverageWidget extends Widget
{
    protected static string $view = 'filament-tax::widgets.zone-coverage';

    protected function getViewData(): array
    {
        $zones = TaxZone::with('rates.taxClass')->get();

        return [
            'zones' => $zones->map(fn ($zone) => [
                'name' => $zone->name,
                'type' => $zone->type->label(),
                'countries' => $zone->countries ?? [],
                'rates' => $zone->rates->map(fn ($r) => [
                    'class' => $r->taxClass->name,
                    'name' => $r->name,
                    'rate' => $r->rate . '%',
                ]),
                'is_default' => $zone->is_default,
            ]),
        ];
    }
}
```

Zone Coverage View:
```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Tax Zone Coverage</x-slot>

        <div class="space-y-4">
            @foreach($zones as $zone)
                <div class="p-4 border rounded-lg @if($zone['is_default']) bg-primary-50 border-primary-200 @endif">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-medium">
                                {{ $zone['name'] }}
                                @if($zone['is_default'])
                                    <x-filament::badge color="primary" size="sm">Default</x-filament::badge>
                                @endif
                            </h4>
                            <p class="text-sm text-gray-500">{{ $zone['type'] }}</p>
                        </div>
                        <div class="text-sm text-gray-600">
                            {{ implode(', ', $zone['countries']) }}
                        </div>
                    </div>
                    
                    <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        @foreach($zone['rates'] as $rate)
                            <div class="bg-gray-50 p-2 rounded">
                                <span class="text-gray-500">{{ $rate['class'] }}:</span>
                                <span class="font-medium">{{ $rate['rate'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## Navigation

**Previous:** [02-tax-zone-resource.md](02-tax-zone-resource.md)  
**Back to:** [01-executive-summary.md](01-executive-summary.md)

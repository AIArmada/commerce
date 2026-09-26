---
title: Widgets
---

# Widgets

The plugin includes three dashboard widgets for tax monitoring and overview.

## Enabling Widgets

Widgets are enabled by default. Control via plugin configuration:

```php
FilamentTaxPlugin::make()
    ->widgets(true);  // Enable all widgets
    
FilamentTaxPlugin::make()
    ->widgets(false); // Disable all widgets
```

Or via configuration file:

```php
// config/filament-tax.php
'features' => [
    'widgets' => true,
],
```

## Tax Stats Widget

Overview statistics showing counts of tax entities.

### Display

```
┌─────────────────────────────────────────────────────────────┐
│ Tax Overview                                                 │
├──────────────┬──────────────┬──────────────┬────────────────┤
│ 🌍 5         │ 📊 12        │ 🏷️ 4        │ 🛡️ 8          │
│ Tax Zones    │ Tax Rates    │ Tax Classes  │ Exemptions     │
│ Active zones │ Configured   │ Product cats │ Approved & valid │
└──────────────┴──────────────┴──────────────┴────────────────┘
```

### Stats Shown

| Stat | Description | Query |
|------|-------------|-------|
| Tax Zones | Count of active zones | `TaxZone::query()->where('is_active', 1)->count()` |
| Tax Rates | Count of active rates | `TaxRate::query()->where('is_active', 1)->count()` |
| Tax Classes | Count of active classes | `TaxClass::query()->where('is_active', 1)->count()` |
| Active Exemptions | Count of approved exemptions inside their validity window | `TaxExemption::query()->where('status', 'approved')` plus `starts_at`/`expires_at` bounds |

### Implementation

`AIArmada\FilamentTax\Widgets\TaxStatsWidget` is `final`; read it as reference, not as a
base class to extend:

```php
namespace AIArmada\FilamentTax\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TaxStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';
    
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        return [
            Stat::make('Tax Zones', number_format($stats['zones']))
                ->description('Active zones')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),
            
            Stat::make('Tax Rates', number_format($stats['rates']))
                ->description('Configured rates')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),
            
            Stat::make('Tax Classes', number_format($stats['classes']))
                ->description('Product categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning'),
            
            Stat::make('Active Exemptions', number_format($stats['exemptions']))
                ->description('Approved & valid')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('gray'),
        ];
    }
}
```

---

## Expiring Exemptions Widget

Table widget showing exemptions that will expire within the next 30 days.

### Display

```
┌───────────────────────────────────────────────────────────────────┐
│ Expiring Exemptions (30 Days)                                     │
├──────────────────┬──────────────────┬────────────┬─────────────────┤
│ Customer         │ Certificate #    │ Reason     │ Expires         │
├──────────────────┼──────────────────┼────────────┼─────────────────┤
│ Acme Corp        │ GOV-2024-001234  │ Non-profit │ 15 Jan 2025     │
│ Tech Solutions   │ CHAR-2024-12345  │ Charity    │ 20 Jan 2025     │
│ Global Trade Ltd │ RES-2024-00077   │ Reseller   │ 1 Feb 2025      │
└──────────────────┴──────────────────┴────────────┴─────────────────┘
```

### Columns

| Column | Description |
|--------|-------------|
| Customer | Exemptable entity name (`exemptable.full_name`) |
| Certificate # | `certificate_number` |
| Reason | `reason`, truncated |
| Expires | `expires_at` as `d M Y`, with a `diffForHumans` description |

The widget has no `Zone` column and no row actions.

### Implementation

```php
namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxExemption;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class ExpiringExemptionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Expiring Exemptions (30 Days)';
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('exemptable.full_name')->label('Customer'),
                TextColumn::make('certificate_number')->label('Certificate #'),
                TextColumn::make('reason')->limit(30),
                TextColumn::make('expires_at')->label('Expires')->date('d M Y'),
            ]);
    }
    
    protected function getTableQuery(): Builder
    {
        $now = CarbonImmutable::now();
        
        return TaxExemption::query()
            ->with('exemptable')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now->addDays(30))
            ->where('expires_at', '>=', $now)
            ->where('status', 'approved')
            ->orderBy('expires_at');
    }
}
```

> **info**
> Filament v5 removed `getTableColumns()` and `getTableActions()`. Configure the table through the `table(Table $table): Table` method. Row actions live in `->recordActions([...])`, and `Filament\Tables\Actions\*` is the v3 namespace — use `Filament\Actions\*`.

### Configuration

The 30-day window is hardcoded. Replace the widget with your own `TableWidget`:

```php
namespace App\Filament\Widgets;

use AIArmada\Tax\Models\TaxExemption;
use Filament\Widgets\TableWidget;

class ExpiringExemptionsWidget extends TableWidget
{
    protected static ?string $heading = 'Expiring Exemptions (60 Days)';
    
    protected int | string | array $columnSpan = 'full';
    
    // ... table() / getTableQuery() as above, with addDays(60)
}
```

> **warning**
> Do not `extend` the package widget. `AIArmada\FilamentTax\Widgets\ExpiringExemptionsWidget` is `final`, as are `TaxStatsWidget` and `ZoneCoverageWidget`. Register your own widget class with `Livewire::component('...')` or in the panel's `->widgets([...])` instead.

---

## Zone Coverage Widget

Visual overview of all tax zones and their configured rates.

### Display

```
┌─────────────────────────────────────────────────────────────┐
│ Zone Coverage                                                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 🌍 Malaysia (MY)                           [Default] ✓  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ Countries: MY                                           │ │
│ │ States: —                                               │ │
│ │ Postcodes: —                                            │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ Rates:                                                  │ │
│ │ • SST 6% (standard) - 6.00%                            │ │
│ │ • SST 6% (digital) - 6.00%                             │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                              │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 🌍 Singapore (SG)                                    ✓  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ Countries: SG                                           │ │
│ │ Rates:                                                  │ │
│ │ • GST (standard) - 9.00%                               │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                              │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 🌍 EU Zone (EU)                                      ✗  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ Countries: DE, FR, IT, ES, NL, BE, AT                  │ │
│ │ Rates:                                                  │ │
│ │ • VAT (standard) - 20.00%                              │ │
│ │ • VAT Reduced (reduced) - 10.00%                       │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Features

- Shows all active zones with their geographic criteria
- Lists all rates per zone with tax class and percentage
- Indicates default zone
- Priority-ordered display, capped at 50 zones

### Implementation

The widget uses a Blade view for flexible rendering:

```php
namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxZone;
use Filament\Widgets\Widget;

final class ZoneCoverageWidget extends Widget
{
    /** @var view-string */
    protected string $view = 'filament-tax::widgets.zone-coverage';
    
    protected int | string | array $columnSpan = 'full';
    
    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $zones = TaxZone::query()
            ->with('rates')
            ->active()
            ->orderBy('priority', 'desc')
            ->limit(50)
            ->get();

        // formatZones() maps each zone to a plain array; rates are preformatted
        return ['zones' => $this->formatZones($zones)];
    }
}
```

The view receives `$zones` as an array of shaped arrays (`id`, `name`, `code`, `type`,
`countries`, `states`, `priority`, `is_default`, `rates`, `rate_count`), not Eloquent
models. `type` is already ucfirst'd, and each rate is `['name', 'class', 'rate', 'is_compound']`
with `rate` preformatted as a percentage string (basis points ÷ 100).

### Blade Template

Published to `resources/views/vendor/filament-tax/widgets/zone-coverage.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Zone Coverage">
        <div class="space-y-4">
            @foreach ($zones as $zone)
                <div class="border rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="font-bold">
                            🌍 {{ $zone['name'] }} ({{ $zone['code'] }})
                        </h3>
                        <div class="flex gap-2">
                            @if ($zone['is_default'])
                                <span class="badge badge-primary">Default</span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600 mb-2">
                        <div>Countries: {{ collect($zone['countries'])->join(', ') ?: '—' }}</div>
                        @if ($zone['states'])
                            <div>States: {{ collect($zone['states'])->join(', ') }}</div>
                        @endif
                    </div>
                    
                    @if ($zone['rates'])
                        <div class="mt-2">
                            <div class="font-medium">Rates:</div>
                            <ul class="list-disc list-inside text-sm">
                                @foreach ($zone['rates'] as $rate)
                                    <li>
                                        {{ $rate['name'] }} ({{ $rate['class'] }}) - {{ $rate['rate'] }}
                                        @if ($rate['is_compound']) [compound] @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="text-sm text-gray-400 italic">No rates configured</div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## Widget Placement

Widgets appear on the dashboard by default. To control placement:

### Dashboard Sort

The package sets `$sort` on all three widgets: `TaxStatsWidget` = 1,
`ExpiringExemptionsWidget` = 2, `ZoneCoverageWidget` = 3. Override in your own widget
class rather than editing the package:

```php
class MyTaxStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10; // Lower = higher on dashboard
}
```

### Column Span

```php
// Full width
protected int | string | array $columnSpan = 'full';

// Half width (2 columns)
protected int | string | array $columnSpan = 2;

// Responsive
protected int | string | array $columnSpan = [
    'sm' => 'full',
    'md' => 2,
    'lg' => 1,
];
```

### Conditional Display

```php
public static function canView(): bool
{
    return auth()->user()->can('view', TaxZone::class);
}
```

---

## Custom Widgets

`TaxStatsWidget` exposes `$sort`, `$pollingInterval`, and `getStats()`. Because it is
`final`, replace it rather than extend it — turn the built-ins off and register your own:

```php
// Admin panel provider
use AIArmada\FilamentTax\FilamentTaxPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentTaxPlugin::make()->widgets(false), // Disable all built-in widgets
        ])
        ->widgets([
            App\Filament\Widgets\CustomTaxStatsWidget::class,
        ]);
}
```

Or write a standalone `StatsOverviewWidget`:

```php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomTaxStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Revenue Collected', '$45,230')
                ->description('This month')
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}
```

All count queries in the package widgets go through Eloquent, so the `OwnerScope`
global scope keeps them tenant-safe when `tax.features.owner.enabled` is `true`.

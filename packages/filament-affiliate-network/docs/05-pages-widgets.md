---
title: Pages & Widgets
---

# Pages & Widgets

## Pages

### MerchantDashboardPage

Analytics dashboard for merchants.

The dashboard is owner-scoped to the current merchant. It does not embed the
network-wide widgets, so its counts and pending applications cannot mix
merchants.

**Features:**
- Site overview
- Offer performance
- Application statistics
- Click/conversion metrics

**URL:** `/affiliate-network/merchant-dashboard`

**Customization:**

Override the view:

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

Edit `resources/views/vendor/filament-affiliate-network/pages/merchant-dashboard.blade.php`.

---

## Widgets

### NetworkStatsWidget

Overview statistics for the entire network.

**Metrics:**
- Active Sites (verified)
- Active Offers (live)
- Pending Applications (awaiting review)
- Total Clicks (network-wide)
- Conversion Rate (clicks to conversions)
- Total Revenue (tracked revenue)

**Sort Order:** 1 (appears first on dashboard)

This is a network-wide admin report. It uses the explicit global context and a
30-second owner-keyed cache.

Total Revenue groups link revenue by link currency. Single-currency networks
show the raw sum; mixed networks convert to
`affiliate-network.currency.default`, or show `—` with a "set exchange rates"
hint when a rate is missing. Configure static rates under
`commerce-support.currency.exchange_rates`, or bind a custom
`ExchangeRateProvider`.

**Usage:**

Register on your dashboard:

```php
namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            NetworkStatsWidget::class,
            // Other widgets...
        ];
    }
}
```

**Customization:**

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget as BaseWidget;

class NetworkStatsWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    
    protected function getStats(): array
    {
        $stats = parent::getStats();
        
        // Add custom stat
        $stats[] = Stat::make('Custom', $this->customValue())
            ->icon('heroicon-o-star');
            
        return $stats;
    }
}
```

---

### TopOffersWidget

Display top performing offers.

This is a network-wide admin leaderboard with the same explicit-global and
30-second owner-keyed cache policy as `NetworkStatsWidget`.

**Features:**
- Top 10 offers by clicks, with per-offer conversions and revenue
- Revenue formatted in each offer's own currency

The leaderboard shows the cached top 10 by clicks in that order. The metric
columns are deliberately not sortable: sorting would only reorder the cached
set and hide the true leaders outside it.

---

## Widget Authorization

Control widget visibility:

```php
use Filament\Widgets\Widget;

class NetworkStatsWidget extends Widget
{
    public static function canView(): bool
    {
        return auth()->user()->hasRole('admin');
    }
}
```

---

## Adding Custom Widgets

Create a new widget:

```php
namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;

class ConversionsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Conversions Over Time';
    
    protected function getData(): array
    {
        $data = AffiliateOfferLink::selectRaw('DATE(created_at) as date, SUM(conversions) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        return [
            'datasets' => [
                [
                    'label' => 'Conversions',
                    'data' => $data->pluck('total'),
                    'borderColor' => '#6366f1',
                ],
            ],
            'labels' => $data->pluck('date')->map(fn ($d) => $d->format('M d')),
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}
```

Register in plugin or panel:

```php
$panel->widgets([
    ConversionsChartWidget::class,
]);
```

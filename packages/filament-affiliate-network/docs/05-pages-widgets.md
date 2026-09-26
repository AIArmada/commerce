---
title: Pages & Widgets
---

# Pages & Widgets

## Pages

The plugin registers exactly one page: `MerchantDashboardPage`. There is no
marketplace/discovery page in this package.

### MerchantDashboardPage

Analytics dashboard for merchants.

The dashboard is owner-scoped to the current merchant. It does not embed the
network-wide widgets, so its counts and pending applications cannot mix
merchants.

**Features:**
- Stats overview: Sites, Verified Sites, Active Offers, Pending Applications
- 5 most recent pending applications
- Top 5 offers by application count

**Methods:**

```php
$this->getSitesCount();              // int
$this->getVerifiedSitesCount();      // int
$this->getActiveOffersCount();       // int
$this->getPendingApplicationsCount();// int
$this->getRecentApplications();      // Collection<AffiliateOfferApplication>
$this->getTopOffers();               // Collection<AffiliateOffer>
$this->getStats();                   // Stat[]
```

**URL:** `/affiliate-network/merchant-dashboard`

Access is gated by `NetworkAdminAccess::allows()` — it reads
`filament-affiliate-network.authorization.admin_ability` (default
`affiliate-network.admin`). `canAccess()` also enforces the panel's own
authorization.

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

This is a network-wide admin report gated by
`NetworkAdminAccess::allows()`. It uses `OwnerCache::remember(null, ...)` for a
30-second cache.

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

`NetworkStatsWidget` is `final` — it cannot be extended. Build your own widget
instead and reuse the shared aggregator:

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Support\NetworkStatsAggregator;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetworkStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return NetworkAdminAccess::allows();
    }

    protected function getStats(): array
    {
        $aggregated = NetworkStatsAggregator::aggregate();

        return [
            Stat::make('Custom', number_format($aggregated['activeSites']))
                ->icon('heroicon-o-star'),
        ];
    }
}
```

---

### TopOffersWidget

Display top performing offers.

This is a network-wide admin leaderboard gated by
`NetworkAdminAccess::allows()`, with the same `OwnerCache::remember(null, ...)`
30-second cache policy as `NetworkStatsWidget`. It is `final` — build your own
widget rather than extending it.

**Sort Order:** 2

**Features:**
- Top 10 offers by clicks, with per-offer conversions and revenue
- Revenue formatted in each offer's own currency

The leaderboard shows the cached top 10 by clicks in that order. The metric
columns are deliberately not sortable: sorting would only reorder the cached
set and hide the true leaders outside it.

---

## Widget Authorization

Both shipped widgets delegate to `NetworkAdminAccess::allows()`. A custom
widget should do the same so it matches:

```php
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Filament\Widgets\Widget;

class MyWidget extends Widget
{
    public static function canView(): bool
    {
        return NetworkAdminAccess::allows();
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

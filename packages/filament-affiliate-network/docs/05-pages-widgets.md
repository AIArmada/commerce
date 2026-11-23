---
title: Pages & Widgets
---

# Pages & Widgets

## Pages

The plugin registers exactly one page: `MerchantDashboardPage`. There is no
marketplace/discovery page. Merchant statements are a **resource**, not a page
(see [usage](04-usage.md)).

### MerchantDashboardPage

Owner-scoped analytics for the current merchant. It does not embed the
network-wide widgets, so its counts cannot mix merchants.

**Features**

- Stats overview: sites, verified sites, active offers, pending applications
- 5 most recent pending applications
- Top 5 offers by application count

**Public methods**

```php
$this->getStats(): array;              // ['sites'=>…, 'verifiedSites'=>…, …]
$this->getRecentApplications();        // Collection<AffiliateOfferApplication>
$this->getTopOffers();                 // Collection<AffiliateOffer>
$this->getSitesCount(): int;
$this->getVerifiedSitesCount(): int;
$this->getActiveOffersCount(): int;
$this->getPendingApplicationsCount(): int;
```

**URL:** `/affiliate-network/merchant-dashboard`

Access is gated by `NetworkAdminAccess::allows()`, which reads
`filament-affiliate-network.authorization.admin_ability` (default
`affiliate-network.admin`). `canAccess()` also enforces the panel's own
authorization.

**Customize the view**

```bash
php artisan vendor:publish --tag=filament-affiliate-network-views
```

Edit `resources/views/vendor/filament-affiliate-network/pages/merchant-dashboard.blade.php`.

---

## Widgets

Both widgets are network-wide admin reports, gated by
`NetworkAdminAccess::allows()` and cached for 30 seconds with
`OwnerCache::remember(null, ...)`. They are `final`.

### NetworkStatsWidget

Six stat cards: Active Sites (verified), Active Offers (live), Pending
Applications, Total Clicks, Conversion Rate, Total Revenue.

`NetworkStatsAggregator::aggregate()` returns:

```php
[
    'activeSites', 'activeOffers', 'pendingApplications',
    'totalClicks', 'totalConversions', 'totalRevenue',
    'conversionRate', 'revenueFormatted', 'revenueCurrency',
    'revenueConverted', 'revenueByCurrency',
]
```

Total Revenue groups link revenue by currency. Single-currency networks show the
raw sum; mixed networks convert to `affiliate-network.currency.default`, or show
`—` with a "set exchange rates" hint when a rate is missing. Configure static
rates under `commerce-support.currency.exchange_rates`, or bind a custom
`ExchangeRateProvider`.

**Sort order:** 1.

**Register**

```php
namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use AIArmada\FilamentAffiliateNetwork\Widgets\NetworkStatsWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [NetworkStatsWidget::class];
    }
}
```

**Custom widget**

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkStatsAggregator;
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
        $stats = NetworkStatsAggregator::aggregate();

        return [
            Stat::make('Active sites', number_format($stats['activeSites']))
                ->icon('heroicon-o-star'),
        ];
    }
}
```

### TopOffersWidget

Top 10 offers by clicks with per-offer conversions and revenue, each formatted in
the offer's own currency.

**Sort order:** 2.

The leaderboard shows the cached top 10 by clicks in that order; metric columns
are deliberately not sortable (sorting would only reorder the cached set and
hide the true leaders outside it).

**Custom**

```php
namespace App\Filament\Widgets;

use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Filament\Widgets\TableWidget;
use Filament\Tables\Table;

class TopOffersWidget extends TableWidget
{
    public static function canView(): bool
    {
        return NetworkAdminAccess::allows();
    }

    public function table(Table $table): Table
    {
        return $table->query(
            \AIarmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer::query()
                ->where('status', \AIarmada\AffiliateNetwork\Data\OfferStatus::Published)
                ->limit(10)
        );
    }
}
```

---

## Widget authorization

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

## Adding a chart widget

```php
namespace App\Filament\Widgets;

use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use Filament\Widgets\ChartWidget;

class ConversionsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Conversions Over Time';

    protected function getData(): array
    {
        $data = AffiliateOfferLink::query()
            ->selectRaw('DATE(created_at) as date, SUM(conversions) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Conversions',
                'data' => $data->pluck('total')->all(),
                'borderColor' => '#6366f1',
            ]],
            'labels' => $data->pluck('date')->map(fn ($date) => $date->format('M d'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

Register on the panel:

```php
$panel->widgets([
    ConversionsChartWidget::class,
]);
```

---
title: Pages & Widgets
---

# Pages & Widgets

## Default Components

The plugin registers these components automatically:

| Type | Component | Description |
|------|-----------|-------------|
| Page | AnalyticsDashboardPage | Revenue analytics dashboard |
| Widget | ChipStatsWidget | Core payment metrics |
| Widget | RevenueChartWidget | Revenue over time chart |
| Widget | RecentTransactionsWidget | Latest transactions table |

## Dashboard Page

### AnalyticsDashboardPage

Comprehensive analytics dashboard with revenue metrics and charts.

**Features:**
- Period filtering (7d, 30d, 90d; any other value falls back to 30d)
- Revenue totals and growth comparison
- Transaction count and average value
- Payment method distribution

**Customization:**

```php
<?php

namespace App\Filament\Pages;

use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage as BasePage;

class AnalyticsDashboardPage extends BasePage
{
    protected function getHeaderWidgets(): array
    {
        return [
            CustomRevenueWidget::class,
            ...parent::getHeaderWidgets(),
        ];
    }
}
```

## Registered Widgets

### ChipStatsWidget

Core metrics stats overview:

- Today's Revenue (paid purchases today)
- This Week (last 7 days)
- This Month (current month)
- Success Rate (paid vs failed)

`ChipStatsWidget` is `final`, so it cannot be subclassed. To add a metric, build your own
`StatsOverviewWidget` and register it alongside the packaged widget:

Package widgets are `final`, so build your own widget for custom metrics:

```php
<?php

namespace App\Filament\Widgets;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentChip\Support\PurchaseRevenueExpressions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CustomChipStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Totals live in the `purchase` JSON payload, so aggregate through the
        // driver-aware expression rather than a plain column.
        $query = Purchase::query()
            ->whereIn('status', ['paid', 'cleared', 'settled'])
            ->where('is_test', false);

        $revenueMinor = (int) $query->sum(DB::raw(PurchaseRevenueExpressions::totalMinor($query)));

        return [
            Stat::make('Lifetime Revenue', MoneyFormatter::formatMinor(
                $revenueMinor,
                config('filament-chip.default_currency', 'MYR'),
            ))->icon('heroicon-o-star'),
        ];
    }
}
```

`Purchase` carries the `HasOwner` trait, so a query inside an owner context is already
owner-scoped.

### RevenueChartWidget

Line/area chart showing revenue over the last 30 days.

- Daily buckets aggregated in SQL
- Currency formatting

## Caching

Revenue numbers, payment-method breakdowns, navigation badges, and
distinct filter options are cached per owner (60–300 seconds), so
dashboard renders stay constant-time as purchase volume grows.
Purchase exports are owner-scoped and exclude the signed checkout URL.

### RecentTransactionsWidget

Table widget showing latest transactions with amount, status, and timestamp.

## Optional Widgets

These widgets are available but not registered by default. Add them to your panel or page manually:

| Widget | Description |
|--------|-------------|
| AccountBalanceWidget | Current account balance display |
| AccountTurnoverWidget | Account turnover metrics |
| BankAccountStatusWidget | Bank account verification status |
| PaymentMethodsWidget | Payment method distribution chart |
| PayoutAmountWidget | Total payout amounts |
| PayoutStatsWidget | Payout statistics overview |
| RecentPayoutsWidget | Latest payouts table |
| TokenStatsWidget | Saved token statistics |

### Adding Optional Widgets

**On a Dashboard Page:**

```php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use AIArmada\FilamentChip\Widgets\PayoutStatsWidget;
use AIArmada\FilamentChip\Widgets\PaymentMethodsWidget;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            PayoutStatsWidget::class,
            PaymentMethodsWidget::class,
        ];
    }
}
```

## Widget Customization

All packaged widgets are `final`, so set these on your own widget classes rather than by
extending `ChipStatsWidget` or `RevenueChartWidget`.

### Column Span

Control widget column span:

```php
class MyStatsWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';
    // Options: 1, 2, 3, 'full', ['md' => 2, 'xl' => 3]
}
```

### Sort Order

Control widget display order:

```php
class MyStatsWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected static ?int $sort = 1;
}

class MyChartWidget extends \Filament\Widgets\ChartWidget
{
    protected static ?int $sort = 2;
}
```

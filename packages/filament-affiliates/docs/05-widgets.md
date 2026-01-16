---
title: Widgets
---

# Widgets

The plugin provides six dashboard widgets for affiliate analytics.

## AffiliateStatsWidget

Overview statistics for affiliate performance.

### Metrics

- Total Affiliates (with growth trend)
- Active Affiliates
- Total Conversions
- Total Commission Paid
- Conversion Rate (optional)

### Configuration

```php
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;

class AffiliateStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    // Customize time period
    protected function getDateRange(): array
    {
        return [
            now()->subDays(30),
            now(),
        ];
    }
}
```

### Usage

Register in your panel:

```php
FilamentAffiliatesPlugin::make()
    ->widgets([
        AffiliateStatsWidget::class,
    ]);
```

## AffiliatePerformanceWidget

Chart showing affiliate performance over time.

### Chart Types

- Line chart (conversions over time)
- Bar chart (top affiliates)
- Comparison with previous period

### Customization

```php
use AIArmada\FilamentAffiliates\Widgets\AffiliatePerformanceWidget;

class AffiliatePerformanceWidget extends BaseWidget
{
    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Conversions',
                    'data' => $this->getConversionData(),
                    'borderColor' => '#6366f1',
                ],
                [
                    'label' => 'Commission',
                    'data' => $this->getCommissionData(),
                    'borderColor' => '#10b981',
                ],
            ],
            'labels' => $this->getLabels(),
        ];
    }
}
```

## FraudAlertWidget

Display recent fraud signals requiring attention.

### Features

- Badge count of unreviewed signals
- Severity color coding
- Quick dismiss/confirm actions
- Link to fraud review page

### Configuration

```php
use AIArmada\FilamentAffiliates\Widgets\FraudAlertWidget;

class FraudAlertWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    // Show only critical/high severity
    protected function getSignals(): Collection
    {
        return FraudSignal::query()
            ->whereIn('severity', [FraudSeverity::High, FraudSeverity::Critical])
            ->whereNull('reviewed_at')
            ->latest()
            ->limit(5)
            ->get();
    }
}
```

## PayoutWidget

Pending payouts overview.

### Metrics

- Pending payout count
- Total pending amount
- Processing payouts
- Recently completed

### Quick Actions

```php
protected function getActions(): array
{
    return [
        Action::make('process_all')
            ->label('Process All Pending')
            ->action(fn () => $this->processPendingPayouts())
            ->requiresConfirmation(),

        Action::make('export')
            ->label('Export Batch')
            ->action(fn () => $this->exportPayoutBatch()),
    ];
}
```

## RealTimeWidget

Live conversion tracking (Livewire polling).

### Features

- Auto-refresh every 10 seconds
- Latest conversions feed
- Real-time commission counter
- Active affiliate sessions

### Configuration

```php
use AIArmada\FilamentAffiliates\Widgets\RealTimeWidget;

class RealTimeWidget extends BaseWidget
{
    // Poll interval in seconds
    protected static string $pollingInterval = '10s';

    protected function getRecentConversions(): Collection
    {
        return AffiliateConversion::query()
            ->with('affiliate')
            ->where('created_at', '>=', now()->subHour())
            ->latest()
            ->limit(10)
            ->get();
    }
}
```

## NetworkWidget

MLM/Network structure visualization.

### Features

- Tree view of affiliate hierarchy
- Downline depth indicators
- Network commission totals
- Expand/collapse nodes

### Customization

```php
use AIArmada\FilamentAffiliates\Widgets\NetworkWidget;

class NetworkWidget extends BaseWidget
{
    // Maximum depth to display
    protected int $maxDepth = 5;

    // Root affiliate filter
    protected function getRootAffiliates(): Collection
    {
        return Affiliate::query()
            ->whereNull('referrer_affiliate_id')
            ->withCount('referrals')
            ->having('referrals_count', '>', 0)
            ->get();
    }
}
```

## Registering Widgets

### In Panel Provider

```php
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
use AIArmada\FilamentAffiliates\Widgets;

FilamentAffiliatesPlugin::make()
    ->widgets([
        Widgets\AffiliateStatsWidget::class,
        Widgets\AffiliatePerformanceWidget::class,
        Widgets\FraudAlertWidget::class,
        Widgets\PayoutWidget::class,
    ]);
```

### On Dashboard

Add to your dashboard:

```php
namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use AIArmada\FilamentAffiliates\Widgets;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            Widgets\AffiliateStatsWidget::class,
            Widgets\FraudAlertWidget::class,
            // ... other widgets
        ];
    }
}
```

## Widget Authorization

Control widget visibility:

```php
use Filament\Widgets\Widget;

class AffiliateStatsWidget extends Widget
{
    public static function canView(): bool
    {
        return auth()->user()->can('viewAffiliateStats');
    }
}
```

## Custom Widgets

Create your own affiliate widgets:

```php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use AIArmada\Affiliates\Models\Affiliate;

class TopAffiliatesWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $top = Affiliate::query()
            ->withSum('conversions', 'amount')
            ->orderByDesc('conversions_sum_amount')
            ->limit(3)
            ->get();

        return $top->map(fn ($affiliate) => Stat::make(
            $affiliate->name,
            money($affiliate->conversions_sum_amount)
        ))->toArray();
    }
}
```

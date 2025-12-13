# Filament Affiliates - Widgets

> **Document:** 03 of 06  
> **Package:** `aiarmada/filament-affiliates`  
> **Status:** ✅ Complete  
> **Last Updated:** December 2025

---

## Overview

Dashboard widgets provide real-time insights into affiliate program performance, fraud alerts, and pending actions.

---

## Widget Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                   AFFILIATE DASHBOARD                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │               AFFILIATE STATS WIDGET (Full Width)          │ │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐      │ │
│  │  │  Total   │ │  Active  │ │ Pending  │ │ Revenue  │      │ │
│  │  │   156    │ │    98    │ │  12,450  │ │ RM 45K   │      │ │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘      │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌──────────────────────────┐ ┌──────────────────────────────┐ │
│  │  PERFORMANCE OVERVIEW    │ │  REAL-TIME ACTIVITY          │ │
│  │  (Charts & Metrics)      │ │  (Live Feed)                 │ │
│  └──────────────────────────┘ └──────────────────────────────┘ │
│                                                                  │
│  ┌──────────────────────────┐ ┌──────────────────────────────┐ │
│  │  FRAUD ALERTS            │ │  PAYOUT QUEUE                │ │
│  │  (Priority List)         │ │  (Pending Payouts)           │ │
│  └──────────────────────────┘ └──────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │             NETWORK VISUALIZATION (Full Width)             │ │
│  │  (Interactive Network Tree)                                 │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## AffiliateStatsWidget

Main dashboard overview widget with key metrics.

```php
class AffiliateStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $aggregator = app(AffiliateStatsAggregator::class);
        
        return [
            Stat::make('Total Affiliates', $aggregator->getTotalCount())
                ->description('All registered affiliates')
                ->icon('heroicon-o-users'),
            
            Stat::make('Active Affiliates', $aggregator->getActiveCount())
                ->description($aggregator->getActivePercentage() . '% of total')
                ->icon('heroicon-o-user-group')
                ->color('success'),
            
            Stat::make('Pending Commissions', $aggregator->getPendingCommissions())
                ->description('Awaiting approval')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            
            Stat::make('Total Paid', $aggregator->getTotalPaid())
                ->description('All-time payouts')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
```

### Metrics Provided

| Metric | Description |
|--------|-------------|
| Total Affiliates | Count of all affiliates |
| Active Affiliates | Count with Active status |
| Pending Commissions | Sum of pending conversion commissions |
| Total Paid | Sum of completed payouts |

---

## PerformanceOverviewWidget

Performance metrics and charts.

### Metrics

| Metric | Description |
|--------|-------------|
| Conversion Rate | Clicks to conversions ratio |
| Average Order Value | Mean order amount |
| Commission Rate | Average commission percentage |
| Top Performers | Leaderboard by revenue |

### Chart Data

```php
protected function getData(): array
{
    return [
        'datasets' => [
            [
                'label' => 'Conversions',
                'data' => $this->getConversionTrend(),
                'borderColor' => '#10B981',
            ],
            [
                'label' => 'Revenue',
                'data' => $this->getRevenueTrend(),
                'borderColor' => '#3B82F6',
            ],
        ],
        'labels' => $this->getDateLabels(),
    ];
}
```

---

## RealTimeActivityWidget

Live activity feed with polling.

```php
class RealTimeActivityWidget extends Widget
{
    protected static ?string $pollingInterval = '30s';
    
    public function getActivities(): Collection
    {
        return collect([
            $this->getRecentConversions(),
            $this->getRecentSignups(),
            $this->getRecentPayouts(),
        ])->flatten()->sortByDesc('created_at')->take(10);
    }
}
```

### Activity Types

| Type | Icon | Color |
|------|------|-------|
| New Conversion | 💰 | Green |
| New Signup | 👤 | Blue |
| Payout Completed | ✅ | Green |
| Fraud Alert | ⚠️ | Red |
| Rank Upgrade | ⭐ | Gold |

---

## FraudAlertWidget

Priority fraud alerts requiring attention.

```php
class FraudAlertWidget extends Widget
{
    public function getAlerts(): Collection
    {
        return AffiliateFraudSignal::query()
            ->where('status', FraudSignalStatus::Detected)
            ->orderByDesc('severity')
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get();
    }
}
```

### Alert Display

```
┌────────────────────────────────────────────────────────────────┐
│ 🚨 FRAUD ALERTS (3 pending)                         [View All] │
├────────────────────────────────────────────────────────────────┤
│ 🔴 SELF_REFERRAL     │ AFF-001   │ 100 pts │ 2 min ago        │
│ 🟠 GEO_ANOMALY       │ AFF-042   │ 40 pts  │ 15 min ago       │
│ 🟡 CLICK_VELOCITY    │ AFF-089   │ 30 pts  │ 1 hour ago       │
└────────────────────────────────────────────────────────────────┘
```

### Severity Colors

| Severity | Color | Icon |
|----------|-------|------|
| Critical | Red | 🔴 |
| High | Orange | 🟠 |
| Medium | Yellow | 🟡 |
| Low | Gray | ⚪ |

---

## PayoutQueueWidget

Pending payouts awaiting processing.

```php
class PayoutQueueWidget extends Widget
{
    public function getPendingPayouts(): Collection
    {
        return AffiliatePayout::query()
            ->where('status', 'pending')
            ->with('affiliate')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();
    }
    
    public function getTotalPending(): string
    {
        $total = AffiliatePayout::where('status', 'pending')
            ->sum('amount_minor');
        
        return Number::currency($total / 100, 'MYR');
    }
}
```

### Queue Display

```
┌────────────────────────────────────────────────────────────────┐
│ 💰 PAYOUT QUEUE                      Total: RM 12,450.00       │
├────────────────────────────────────────────────────────────────┤
│ AFF-001 │ John Doe      │ RM 2,500.00 │ Bank Transfer │ [Pay] │
│ AFF-042 │ Jane Smith    │ RM 1,200.00 │ PayPal        │ [Pay] │
│ AFF-089 │ Ali Ahmad     │ RM 890.50   │ Stripe        │ [Pay] │
└────────────────────────────────────────────────────────────────┘
```

---

## NetworkVisualizationWidget

Interactive network tree visualization.

### Features

| Feature | Description |
|---------|-------------|
| Expandable Nodes | Click to expand/collapse children |
| Node Details | Hover for affiliate details |
| Revenue Display | Show revenue per node |
| Team Size | Display downline count |
| Rank Badges | Visual rank indicators |

### Node Data Structure

```php
public function buildTreeData(): array
{
    return [
        'id' => $affiliate->id,
        'name' => $affiliate->name,
        'code' => $affiliate->code,
        'rank' => $affiliate->rank?->name,
        'revenue' => $affiliate->total_revenue,
        'team_size' => $affiliate->children_count,
        'children' => $this->buildChildren($affiliate),
    ];
}
```

### Blade View

```blade
{{-- resources/views/widgets/network-visualization.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <div x-data="networkTree(@js($this->getTreeData()))">
            <div class="network-container">
                @include('filament-affiliates::widgets.partials.network-node', [
                    'node' => $rootNode,
                    'level' => 0,
                ])
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## Widget Registration

Widgets are registered via the plugin and can be added to any panel:

```php
class FilamentAffiliatesPlugin implements Plugin
{
    public function register(Panel $panel): void
    {
        $panel->widgets([
            AffiliateStatsWidget::class,
            PerformanceOverviewWidget::class,
            RealTimeActivityWidget::class,
            FraudAlertWidget::class,
            PayoutQueueWidget::class,
            NetworkVisualizationWidget::class,
        ]);
    }
}
```

---

## Navigation

**Previous:** [02-resources.md](02-resources.md)  
**Next:** [04-pages.md](04-pages.md)

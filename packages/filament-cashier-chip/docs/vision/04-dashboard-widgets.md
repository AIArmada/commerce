# Filament Cashier Chip Vision: Dashboard Widgets

> **Document:** 04 of 05  
> **Package:** `aiarmada/filament-cashier-chip`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Overview

The Dashboard Widgets module provides real-time revenue analytics and subscription metrics for the admin panel. These widgets aggregate data from local `cashier_subscriptions` and `cashier_invoices` tables, providing actionable insights without external API calls.

---

## Widget Overview

### Admin Dashboard Layout

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ 📊 Billing Dashboard                                                            │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐        │
│  │ 💰 MRR        │ │ 👥 Active     │ │ 📈 Growth     │ │ 📉 Churn      │        │
│  │               │ │ Subscribers   │ │               │ │ Rate          │        │
│  │ RM 45,230     │ │ 457           │ │ +12.5%        │ │ 2.3%          │        │
│  │ ↑ 8.2%        │ │ ↑ 23 new      │ │ vs last month │ │ ↓ 0.5%        │        │
│  └───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘        │
│                                                                                 │
│  ┌─────────────────────────────────────────┐ ┌─────────────────────────────────┐│
│  │ 📈 Revenue Trend (30 days)              │ │ 🥧 Plan Distribution            ││
│  │ ▂▃▅▆▇█▇▆▅▆▇▇█▇▆▅▄▅▆▇▇█▇▆▅▆▇▇█▇         │ │                                 ││
│  │                                          │ │ ● Premium 45%                   ││
│  │ Dec 2025                                │ │ ● Pro 35%                       ││
│  │ ▬ Revenue  ▬ New Subs                   │ │ ● Basic 20%                     ││
│  └─────────────────────────────────────────┘ └─────────────────────────────────┘│
│                                                                                 │
│  ┌─────────────────────────────────────────┐ ┌─────────────────────────────────┐│
│  │ 🎯 Trial Conversions                    │ │ ⚠️ Requiring Attention          ││
│  │                                          │ │                                 ││
│  │ Conversion Rate: 68%                    │ │ 12 Trials ending in 3 days      ││
│  │ Trials Active: 34                       │ │ 5 Past due subscriptions        ││
│  │ Converted this month: 23                │ │ 8 Failed payments               ││
│  └─────────────────────────────────────────┘ └─────────────────────────────────┘│
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## MRR Widget (Monthly Recurring Revenue)

### Display

```
┌─────────────────────────────────────┐
│ 💰 Monthly Recurring Revenue        │
│                                     │
│     RM 45,230.00                    │
│     ↑ 8.2% from last month          │
│                                     │
│     New MRR:      RM 4,850          │
│     Churned MRR:  RM 1,120          │
│     Net MRR:      RM 3,730          │
└─────────────────────────────────────┘
```

### Implementation

```php
class MrrWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $currentMrr = $this->calculateMrr(now());
        $previousMrr = $this->calculateMrr(now()->subMonth());
        $change = $previousMrr > 0 
            ? (($currentMrr - $previousMrr) / $previousMrr) * 100 
            : 0;

        $newMrr = $this->calculateNewMrr();
        $churnedMrr = $this->calculateChurnedMrr();

        return [
            Stat::make('Monthly Recurring Revenue', Number::currency($currentMrr, 'MYR'))
                ->description($change >= 0 ? "↑ {$change}% from last month" : "↓ {$change}% from last month")
                ->descriptionIcon($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($change >= 0 ? 'success' : 'danger')
                ->chart($this->getMrrTrend()),
                
            Stat::make('New MRR', Number::currency($newMrr, 'MYR'))
                ->description('This month')
                ->color('success'),
                
            Stat::make('Churned MRR', Number::currency($churnedMrr, 'MYR'))
                ->description('This month')
                ->color('danger'),
        ];
    }

    protected function calculateMrr(Carbon $date): float
    {
        return Subscription::query()
            ->active()
            ->whereDate('created_at', '<=', $date)
            ->with('items')
            ->get()
            ->sum(fn ($sub) => $sub->items->sum('unit_amount') * $sub->items->sum('quantity'));
    }

    protected function getMrrTrend(): array
    {
        return collect(range(29, 0))
            ->map(fn ($daysAgo) => $this->calculateMrr(now()->subDays($daysAgo)))
            ->toArray();
    }
}
```

---

## Active Subscribers Widget

### Display

```
┌─────────────────────────────────────┐
│ 👥 Active Subscribers               │
│                                     │
│         457                         │
│     ↑ 23 new this month             │
│                                     │
│     On Trial:     34                │
│     Active:       412               │
│     Past Due:     11                │
└─────────────────────────────────────┘
```

### Implementation

```php
class ActiveSubscribersWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $total = Subscription::active()->count();
        $onTrial = Subscription::onTrial()->count();
        $pastDue = Subscription::where('chip_status', 'past_due')->count();
        $newThisMonth = Subscription::where('created_at', '>=', now()->startOfMonth())->count();

        return [
            Stat::make('Total Active', Number::format($total))
                ->description("↑ {$newThisMonth} new this month")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary')
                ->chart($this->getSubscriberTrend()),
                
            Stat::make('On Trial', Number::format($onTrial))
                ->description('Active trials')
                ->color('warning'),
                
            Stat::make('Past Due', Number::format($pastDue))
                ->description('Need attention')
                ->color('danger'),
        ];
    }

    protected function getSubscriberTrend(): array
    {
        return collect(range(29, 0))
            ->map(fn ($daysAgo) => Subscription::query()
                ->active()
                ->whereDate('created_at', '<=', now()->subDays($daysAgo))
                ->count()
            )
            ->toArray();
    }
}
```

---

## Revenue Chart Widget

### Display

```
┌─────────────────────────────────────────────────────────────────┐
│ 📈 Revenue Trend                                   [30d ▾]     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  RM 5,000 ┤                     ╭────╮                          │
│           │              ╭─────╯    ╰─╮                         │
│  RM 4,000 ┤         ╭───╯             ╰───╮                     │
│           │    ╭───╯                       ╰───╮                │
│  RM 3,000 ┤───╯                                 ╰───            │
│           │                                                     │
│  RM 2,000 ┤                                                     │
│           │                                                     │
│  RM 1,000 ┤                                                     │
│           │                                                     │
│         0 ┼────┬────┬────┬────┬────┬────┬────                   │
│           Nov 9  Nov 16  Nov 23  Nov 30  Dec 7                  │
│                                                                 │
│  ─── Revenue    ─── New Subscriptions                           │
└─────────────────────────────────────────────────────────────────┘
```

### Implementation

```php
class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Trend';
    protected static ?int $sort = 3;
    protected int $days = 30;

    protected function getData(): array
    {
        $data = $this->getRevenueData();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data['revenue'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'New Subscriptions',
                    'data' => $data['new_subscriptions'],
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'transparent',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getRevenueData(): array
    {
        $dates = collect(range($this->days - 1, 0))
            ->map(fn ($daysAgo) => now()->subDays($daysAgo)->format('Y-m-d'));

        $revenue = [];
        $newSubscriptions = [];
        $labels = [];

        foreach ($dates as $date) {
            $labels[] = Carbon::parse($date)->format('M d');
            
            $revenue[] = Invoice::query()
                ->paid()
                ->whereDate('paid_at', $date)
                ->sum('total');
                
            $newSubscriptions[] = Subscription::query()
                ->whereDate('created_at', $date)
                ->count();
        }

        return compact('revenue', 'newSubscriptions', 'labels');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "RM " + value.toLocaleString(); }',
                    ],
                ],
                'y1' => [
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
```

---

## Plan Distribution Widget

### Display

```
┌─────────────────────────────────────┐
│ 🥧 Plan Distribution                │
├─────────────────────────────────────┤
│                                     │
│         ┌───────────┐               │
│        ╱ Premium    ╲               │
│       │  45% (206)   │              │
│       │             │               │
│        ╲  Pro 35%  ╱                │
│         │ (160)   │                 │
│          ╲ Basic ╱                  │
│           │ 20% │                   │
│           │(91) │                   │
│            ╲───╱                    │
│                                     │
│  ● Premium (45%)  RM 20,394/mo      │
│  ● Pro (35%)      RM 15,840/mo      │
│  ● Basic (20%)    RM 2,639/mo       │
└─────────────────────────────────────┘
```

### Implementation

```php
class PlanDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Plan Distribution';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $plans = Subscription::query()
            ->active()
            ->select('plan_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('plan_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->plan_id => $row->count
            ]);

        $colors = [
            '#10b981', // Green
            '#6366f1', // Indigo
            '#f59e0b', // Amber
            '#ef4444', // Red
            '#8b5cf6', // Purple
        ];

        return [
            'datasets' => [
                [
                    'data' => $plans->values()->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $plans->count()),
                ],
            ],
            'labels' => $plans->keys()->map(fn ($plan) => $this->getPlanLabel($plan))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getPlanLabel(string $planId): string
    {
        $labels = config('cashier-chip.plan_labels', []);
        return $labels[$planId] ?? Str::title(str_replace(['_', '-'], ' ', $planId));
    }
}
```

---

## Churn Rate Widget

### Display

```
┌─────────────────────────────────────┐
│ 📉 Churn Rate                       │
│                                     │
│         2.3%                        │
│     ↓ 0.5% from last month          │
│                                     │
│     Churned:      11 subscribers    │
│     Churned MRR:  RM 1,089          │
│     Avg. Tenure:  4.2 months        │
└─────────────────────────────────────┘
```

### Implementation

```php
class ChurnRateWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $startOfMonth = now()->startOfMonth();
        $activeAtStart = Subscription::query()
            ->active()
            ->where('created_at', '<', $startOfMonth)
            ->count();
            
        $churnedThisMonth = Subscription::query()
            ->whereBetween('ends_at', [$startOfMonth, now()])
            ->count();
            
        $churnRate = $activeAtStart > 0 
            ? ($churnedThisMonth / $activeAtStart) * 100 
            : 0;

        $previousChurnRate = $this->calculateChurnRate(now()->subMonth());
        $change = $previousChurnRate - $churnRate;

        $churnedMrr = Subscription::query()
            ->whereBetween('ends_at', [$startOfMonth, now()])
            ->with('items')
            ->get()
            ->sum(fn ($sub) => $sub->items->sum('unit_amount'));

        return [
            Stat::make('Churn Rate', number_format($churnRate, 1) . '%')
                ->description($change >= 0 
                    ? "↓ " . number_format(abs($change), 1) . "% improvement"
                    : "↑ " . number_format(abs($change), 1) . "% increase")
                ->color($change >= 0 ? 'success' : 'danger')
                ->chart($this->getChurnTrend()),
                
            Stat::make('Churned', $churnedThisMonth)
                ->description('This month')
                ->color('danger'),
                
            Stat::make('Churned MRR', Number::currency($churnedMrr, 'MYR'))
                ->description('Lost revenue')
                ->color('danger'),
        ];
    }
}
```

---

## Trial Conversions Widget

### Display

```
┌─────────────────────────────────────┐
│ 🎯 Trial Conversions                │
├─────────────────────────────────────┤
│                                     │
│  Conversion Rate                    │
│  ╔══════════════════════════╗       │
│  ║████████████████░░░░░░░░░░║ 68%   │
│  ╚══════════════════════════╝       │
│                                     │
│  Active Trials:      34             │
│  Converted (Month):  23             │
│  Expired (Month):    11             │
│  Avg. Trial Length:  12 days        │
└─────────────────────────────────────┘
```

### Implementation

```php
class TrialConversionsWidget extends Widget
{
    protected static string $view = 'filament-cashier-chip::widgets.trial-conversions';
    protected static ?int $sort = 6;

    public function getData(): array
    {
        $startOfMonth = now()->startOfMonth();
        
        $activeTrials = Subscription::onTrial()->count();
        
        $trialsThatEnded = Subscription::query()
            ->where('trial_ends_at', '>=', $startOfMonth)
            ->where('trial_ends_at', '<=', now())
            ->count();
            
        $converted = Subscription::query()
            ->where('trial_ends_at', '>=', $startOfMonth)
            ->where('trial_ends_at', '<=', now())
            ->whereNull('ends_at') // Still active
            ->count();
            
        $expired = $trialsThatEnded - $converted;
        
        $conversionRate = $trialsThatEnded > 0 
            ? ($converted / $trialsThatEnded) * 100 
            : 0;

        return [
            'conversionRate' => round($conversionRate, 1),
            'activeTrials' => $activeTrials,
            'converted' => $converted,
            'expired' => $expired,
            'avgTrialDays' => $this->getAverageTrialLength(),
        ];
    }

    protected function getAverageTrialLength(): float
    {
        return Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(DATEDIFF(trial_ends_at, created_at)) as avg_days')
            ->value('avg_days') ?? 0;
    }
}
```

---

## Attention Required Widget

### Display

```
┌─────────────────────────────────────────────┐
│ ⚠️ Requiring Attention                      │
├─────────────────────────────────────────────┤
│                                             │
│  ⏰ Trials Ending Soon                       │
│  ├─ Ending in 3 days: 12                    │
│  └─ Ending in 7 days: 28                    │
│                                             │
│  💳 Payment Issues                           │
│  ├─ Past due: 5                             │
│  └─ Failed payments: 8                      │
│                                             │
│  📅 Subscriptions Ending Soon               │
│  └─ Grace period ending: 3                  │
│                                             │
│                          [View All Issues]  │
└─────────────────────────────────────────────┘
```

### Implementation

```php
class AttentionRequiredWidget extends Widget
{
    protected static string $view = 'filament-cashier-chip::widgets.attention-required';
    protected static ?int $sort = 7;
    protected static ?string $pollingInterval = '30s';

    public function getData(): array
    {
        return [
            'trialsEndingIn3Days' => Subscription::query()
                ->onTrial()
                ->where('trial_ends_at', '<=', now()->addDays(3))
                ->count(),
                
            'trialsEndingIn7Days' => Subscription::query()
                ->onTrial()
                ->where('trial_ends_at', '<=', now()->addDays(7))
                ->count(),
                
            'pastDue' => Subscription::query()
                ->where('chip_status', 'past_due')
                ->count(),
                
            'failedPayments' => Invoice::query()
                ->where('status', 'payment_failed')
                ->whereDate('created_at', '>=', now()->subDays(30))
                ->count(),
                
            'gracePeriodsEnding' => Subscription::query()
                ->onGracePeriod()
                ->where('ends_at', '<=', now()->addDays(3))
                ->count(),
        ];
    }

    protected function getActions(): array
    {
        return [
            Action::make('viewIssues')
                ->label('View All Issues')
                ->url(SubscriptionResource::getUrl('index', ['activeTab' => 'issues']))
                ->icon('heroicon-o-arrow-right'),
        ];
    }
}
```

---

## Dedicated Dashboard Page

### Implementation

```php
class BillingDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Billing Dashboard';
    protected static ?string $title = 'Billing Analytics';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament-cashier-chip::pages.billing-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-cashier-chip.navigation.group', 'Billing');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MrrWidget::class,
            ActiveSubscribersWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RevenueChartWidget::class,
            PlanDistributionWidget::class,
            TrialConversionsWidget::class,
            AttentionRequiredWidget::class,
            ChurnRateWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportReport')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    DatePicker::make('start_date')
                        ->label('From')
                        ->default(now()->subMonth()),
                    DatePicker::make('end_date')
                        ->label('To')
                        ->default(now()),
                    Select::make('format')
                        ->options([
                            'csv' => 'CSV',
                            'xlsx' => 'Excel',
                            'pdf' => 'PDF',
                        ])
                        ->default('csv'),
                ])
                ->action(function (array $data) {
                    return $this->exportReport($data);
                }),
        ];
    }
}
```

---

## Implementation Checklist

### Phase 1: Core Stats Widgets
- [ ] `MrrWidget` with trend chart
- [ ] `ActiveSubscribersWidget` with breakdown
- [ ] `ChurnRateWidget` with comparison

### Phase 2: Chart Widgets
- [ ] `RevenueChartWidget` with dual axis
- [ ] `PlanDistributionWidget` doughnut chart

### Phase 3: Advanced Widgets
- [ ] `TrialConversionsWidget` with progress bar
- [ ] `AttentionRequiredWidget` with issues list

### Phase 4: Dashboard Page
- [ ] Dedicated dashboard page
- [ ] Widget layout configuration
- [ ] Export report functionality

### Phase 5: Polish
- [ ] Real-time polling
- [ ] Date range filters
- [ ] Widget caching
- [ ] Responsive layout

---

## Navigation

**Previous:** [03-customer-portal.md](03-customer-portal.md)  
**Next:** [05-implementation-roadmap.md](05-implementation-roadmap.md)

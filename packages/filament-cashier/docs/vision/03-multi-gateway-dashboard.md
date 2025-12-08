# Filament Cashier Vision: Multi-Gateway Dashboard

> **Document:** 03 of 05  
> **Package:** `aiarmada/filament-cashier`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Overview

The Multi-Gateway Dashboard provides unified revenue analytics and billing metrics across ALL payment gateways. Instead of checking Stripe Dashboard and CHIP Dashboard separately, admins see combined metrics with gateway breakdowns.

---

## Dashboard Overview

### Layout

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│ 📊 Unified Billing Dashboard                                    [30d ▾] [Export ▾]     │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                         │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐        │
│  │ 💰 Total MRR    │ │ 👥 Total        │ │ 📈 Growth       │ │ 📉 Churn        │        │
│  │ (All Gateways)  │ │ Subscribers     │ │                 │ │ Rate            │        │
│  │ $67,450         │ │ 1,289           │ │ +15.2%          │ │ 1.8%            │        │
│  │ ↑ 12.3%         │ │ ↑ 87 new        │ │ vs last month   │ │ ↓ 0.3%          │        │
│  │                 │ │                 │ │                 │ │                 │        │
│  │ 💳 $45,230      │ │ 💳 832          │ │ 💳 +12%         │ │ 💳 2.1%         │        │
│  │ 🔷 RM 89,440    │ │ 🔷 457          │ │ 🔷 +18%         │ │ 🔷 1.4%         │        │
│  └─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘        │
│                                                                                         │
│  ┌─────────────────────────────────────────────┐ ┌─────────────────────────────────────┐│
│  │ 📈 Revenue by Gateway (30 days)             │ │ 🥧 Gateway Distribution              ││
│  │                                              │ │                                     ││
│  │  $60K ┤                  ╭───── Stripe       │ │         ┌───────────┐               ││
│  │       │         ╭───────╯                    │ │        ╱ Stripe     ╲              ││
│  │  $40K ┤    ╭───╯                             │ │       │  65%        │              ││
│  │       │───╯      ╭────────── CHIP            │ │       │ (832 subs)  │              ││
│  │  $20K ┤    ╭────╯                            │ │        ╲  CHIP 35% ╱               ││
│  │       │───╯                                  │ │         │ (457 subs)│               ││
│  │     0 ┼────┬────┬────┬────┬                  │ │          ╲─────────╱                ││
│  │       Nov   Nov   Dec   Dec                  │ │                                     ││
│  └─────────────────────────────────────────────┘ └─────────────────────────────────────┘│
│                                                                                         │
│  ┌─────────────────────────────────────────────┐ ┌─────────────────────────────────────┐│
│  │ 💳 Stripe Metrics                           │ │ 🔷 CHIP Metrics                     ││
│  │                                              │ │                                     ││
│  │ MRR: $45,230                                │ │ MRR: RM 89,440 (~$20,100)           ││
│  │ Active: 832    Trialing: 45                 │ │ Active: 457    Trialing: 34        ││
│  │ Churn: 2.1%    Conv: 72%                    │ │ Churn: 1.4%    Conv: 68%           ││
│  │                                              │ │                                     ││
│  │ Top Plans:                                  │ │ Top Plans:                          ││
│  │ • Premium Annual  $18,500 (41%)             │ │ • Pro Monthly    RM 45,342 (51%)   ││
│  │ • Pro Monthly     $15,200 (34%)             │ │ • Basic Monthly  RM 28,840 (32%)   ││
│  │ • Basic Monthly   $11,530 (25%)             │ │ • Enterprise     RM 15,258 (17%)   ││
│  └─────────────────────────────────────────────┘ └─────────────────────────────────────┘│
│                                                                                         │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## Total MRR Widget

### Display

```
┌─────────────────────────────────────────────┐
│ 💰 Total Monthly Recurring Revenue          │
│                                             │
│         $67,450                             │
│         ↑ 12.3% from last month             │
│                                             │
│    ┌─────────────────────────────────────┐  │
│    │ 💳 Stripe    $45,230    (67%)       │  │
│    │ 🔷 CHIP      RM 89,440  (33%)       │  │
│    │              (~$20,100 USD)          │  │
│    └─────────────────────────────────────┘  │
│                                             │
│    New MRR:      $8,450                     │
│    Churned MRR:  $2,120                     │
│    Net MRR:      $6,330                     │
└─────────────────────────────────────────────┘
```

### Implementation

```php
class TotalMrrWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $gateways = Cashier::manager()->availableGateways();
        $metrics = $this->calculateMetrics();

        $stats = [
            Stat::make('Total MRR', Number::currency($metrics['total_mrr_usd'], 'USD'))
                ->description($this->formatChange($metrics['mrr_change']))
                ->descriptionIcon($metrics['mrr_change'] >= 0 
                    ? 'heroicon-m-arrow-trending-up' 
                    : 'heroicon-m-arrow-trending-down')
                ->color($metrics['mrr_change'] >= 0 ? 'success' : 'danger')
                ->chart($metrics['mrr_trend']),
        ];

        // Add per-gateway breakdown
        foreach ($gateways as $gateway) {
            $gatewayMetrics = $metrics['gateways'][$gateway] ?? null;
            if ($gatewayMetrics) {
                $stats[] = Stat::make(
                    $this->getGatewayLabel($gateway),
                    Number::currency($gatewayMetrics['mrr'], $gatewayMetrics['currency'])
                )
                    ->description("{$gatewayMetrics['percentage']}% of total")
                    ->color($this->getGatewayColor($gateway));
            }
        }

        return $stats;
    }

    protected function calculateMetrics(): array
    {
        $gateways = Cashier::manager()->availableGateways();
        $gatewayMetrics = [];
        $totalMrrUsd = 0;

        foreach ($gateways as $gateway) {
            $mrr = $this->calculateGatewayMrr($gateway);
            $currency = $this->getGatewayCurrency($gateway);
            $mrrUsd = $this->convertToUsd($mrr, $currency);
            
            $gatewayMetrics[$gateway] = [
                'mrr' => $mrr,
                'mrr_usd' => $mrrUsd,
                'currency' => $currency,
            ];
            
            $totalMrrUsd += $mrrUsd;
        }

        // Calculate percentages
        foreach ($gatewayMetrics as $gateway => &$metrics) {
            $metrics['percentage'] = $totalMrrUsd > 0 
                ? round(($metrics['mrr_usd'] / $totalMrrUsd) * 100, 1)
                : 0;
        }

        return [
            'total_mrr_usd' => $totalMrrUsd,
            'gateways' => $gatewayMetrics,
            'mrr_change' => $this->calculateMrrChange($totalMrrUsd),
            'mrr_trend' => $this->getMrrTrend(),
        ];
    }
}
```

---

## Total Subscribers Widget

### Display

```
┌─────────────────────────────────────────────┐
│ 👥 Total Active Subscribers                 │
│                                             │
│         1,289                               │
│         ↑ 87 new this month                 │
│                                             │
│    ┌─────────────────────────────────────┐  │
│    │ 💳 Stripe    832   Active           │  │
│    │              45    Trialing         │  │
│    │ 🔷 CHIP      457   Active           │  │
│    │              34    Trialing         │  │
│    └─────────────────────────────────────┘  │
│                                             │
│    Total Trialing: 79                       │
│    Converting Soon: 23                      │
└─────────────────────────────────────────────┘
```

### Implementation

```php
class TotalSubscribersWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $metrics = $this->aggregateSubscriberCounts();

        return [
            Stat::make('Total Active', Number::format($metrics['total_active']))
                ->description("↑ {$metrics['new_this_month']} new this month")
                ->color('primary')
                ->chart($metrics['subscriber_trend']),
                
            Stat::make('💳 Stripe', Number::format($metrics['stripe']['active']))
                ->description("{$metrics['stripe']['trialing']} trialing")
                ->color('indigo'),
                
            Stat::make('🔷 CHIP', Number::format($metrics['chip']['active']))
                ->description("{$metrics['chip']['trialing']} trialing")
                ->color('emerald'),
                
            Stat::make('Total Trialing', Number::format($metrics['total_trialing']))
                ->description("{$metrics['converting_soon']} converting in 7 days")
                ->color('warning'),
        ];
    }

    protected function aggregateSubscriberCounts(): array
    {
        $gateways = Cashier::manager()->availableGateways();
        $counts = ['stripe' => ['active' => 0, 'trialing' => 0], 
                   'chip' => ['active' => 0, 'trialing' => 0]];

        foreach ($gateways as $gateway) {
            $counts[$gateway] = [
                'active' => $this->getActiveCount($gateway),
                'trialing' => $this->getTrialingCount($gateway),
            ];
        }

        return [
            'total_active' => array_sum(array_column($counts, 'active')),
            'total_trialing' => array_sum(array_column($counts, 'trialing')),
            'new_this_month' => $this->getNewThisMonth(),
            'converting_soon' => $this->getConvertingSoon(),
            'subscriber_trend' => $this->getSubscriberTrend(),
            'stripe' => $counts['stripe'],
            'chip' => $counts['chip'],
        ];
    }
}
```

---

## Gateway Comparison Chart

### Display

```
┌─────────────────────────────────────────────────────────────────┐
│ 📈 Revenue Comparison by Gateway                   [30d ▾]     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  $60K ┤                              ╭─────────── Stripe        │
│       │                    ╭────────╯                           │
│  $50K ┤              ╭────╯                                     │
│       │         ╭───╯                                           │
│  $40K ┤    ╭───╯                                                │
│       │───╯                                                     │
│                                                                 │
│  $25K ┤                         ╭──────────────── CHIP (USD eq) │
│       │              ╭─────────╯                                │
│  $20K ┤    ╭────────╯                                           │
│       │───╯                                                     │
│                                                                 │
│     0 ┼────┬────┬────┬────┬────┬────┬────                       │
│       Nov 9  Nov 16  Nov 23  Nov 30  Dec 7                      │
│                                                                 │
│  ─── Stripe    ─── CHIP (USD equivalent)                        │
└─────────────────────────────────────────────────────────────────┘
```

### Implementation

```php
class GatewayComparisonWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Comparison by Gateway';
    protected static ?int $sort = 3;
    protected int $days = 30;

    protected function getData(): array
    {
        $data = $this->getComparisonData();

        $datasets = [];
        $colors = [
            'stripe' => '#6366f1',
            'chip' => '#10b981',
        ];

        foreach ($data['gateways'] as $gateway => $values) {
            $datasets[] = [
                'label' => ucfirst($gateway) . ($gateway === 'chip' ? ' (USD eq.)' : ''),
                'data' => $values,
                'borderColor' => $colors[$gateway] ?? '#888',
                'backgroundColor' => 'transparent',
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $data['labels'],
        ];
    }

    protected function getComparisonData(): array
    {
        $dates = collect(range($this->days - 1, 0))
            ->map(fn ($daysAgo) => now()->subDays($daysAgo)->format('Y-m-d'));

        $gateways = Cashier::manager()->availableGateways();
        $data = ['labels' => [], 'gateways' => []];

        foreach ($gateways as $gateway) {
            $data['gateways'][$gateway] = [];
        }

        foreach ($dates as $date) {
            $data['labels'][] = Carbon::parse($date)->format('M d');
            
            foreach ($gateways as $gateway) {
                $revenue = $this->getDailyRevenue($gateway, $date);
                // Convert to USD for comparison
                $revenueUsd = $this->convertToUsd(
                    $revenue, 
                    $this->getGatewayCurrency($gateway)
                );
                $data['gateways'][$gateway][] = $revenueUsd;
            }
        }

        return $data;
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

---

## Gateway Distribution Widget

### Display

```
┌─────────────────────────────────────────────┐
│ 🥧 Subscriber Distribution by Gateway       │
├─────────────────────────────────────────────┤
│                                             │
│           ┌─────────────────┐               │
│          ╱ 💳 Stripe         ╲              │
│         │    65%              │             │
│         │   (832 subs)        │             │
│         │   $45,230 MRR       │             │
│          ╲                   ╱              │
│           ╲  🔷 CHIP 35%    ╱               │
│            │  (457 subs)   │                │
│            │  RM 89,440    │                │
│             ╲─────────────╱                 │
│                                             │
│  By Revenue (USD):                          │
│  💳 Stripe: 69%  |  🔷 CHIP: 31%            │
└─────────────────────────────────────────────┘
```

### Implementation

```php
class GatewayDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Gateway Distribution';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $distribution = $this->calculateDistribution();

        return [
            'datasets' => [
                [
                    'data' => array_values($distribution['counts']),
                    'backgroundColor' => ['#6366f1', '#10b981'],
                ],
            ],
            'labels' => array_map(
                fn ($g) => $this->getGatewayLabel($g), 
                array_keys($distribution['counts'])
            ),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getDescription(): ?string
    {
        $distribution = $this->calculateDistribution();
        $parts = [];
        
        foreach ($distribution['counts'] as $gateway => $count) {
            $pct = $distribution['percentages'][$gateway];
            $parts[] = "{$this->getGatewayEmoji($gateway)} {$gateway}: {$pct}%";
        }
        
        return 'By Revenue (USD): ' . implode(' | ', $parts);
    }
}
```

---

## Individual Gateway Widgets

### Per-Gateway Metrics Card

```php
class GatewayMetricsWidget extends Widget
{
    protected static string $view = 'filament-cashier::widgets.gateway-metrics';
    
    public string $gateway;
    
    public function mount(string $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function getData(): array
    {
        $gateway = Cashier::manager()->gateway($this->gateway);
        
        return [
            'gateway' => $this->gateway,
            'label' => $this->getGatewayLabel($this->gateway),
            'emoji' => $this->getGatewayEmoji($this->gateway),
            'color' => $this->getGatewayColor($this->gateway),
            'mrr' => $this->calculateMrr($gateway),
            'currency' => $this->getGatewayCurrency($this->gateway),
            'active' => $this->getActiveCount($gateway),
            'trialing' => $this->getTrialingCount($gateway),
            'churn_rate' => $this->getChurnRate($gateway),
            'conversion_rate' => $this->getConversionRate($gateway),
            'top_plans' => $this->getTopPlans($gateway),
        ];
    }
}
```

### Blade Template

```blade
{{-- resources/views/widgets/gateway-metrics.blade.php --}}
<x-filament::widget>
    <x-filament::card>
        <div class="flex items-center gap-2 mb-4">
            <span class="text-2xl">{{ $emoji }}</span>
            <h3 class="text-lg font-semibold">{{ $label }} Metrics</h3>
        </div>
        
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <p class="text-sm text-gray-500">MRR</p>
                <p class="text-xl font-bold">{{ Number::currency($mrr, $currency) }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Active</p>
                <p class="text-xl font-bold">{{ $active }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Churn Rate</p>
                <p class="text-xl font-bold">{{ $churn_rate }}%</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Trial Conversion</p>
                <p class="text-xl font-bold">{{ $conversion_rate }}%</p>
            </div>
        </div>
        
        <div>
            <p class="text-sm text-gray-500 mb-2">Top Plans:</p>
            @foreach ($top_plans as $plan)
                <div class="flex justify-between text-sm py-1">
                    <span>• {{ $plan['name'] }}</span>
                    <span>{{ Number::currency($plan['mrr'], $currency) }} ({{ $plan['percentage'] }}%)</span>
                </div>
            @endforeach
        </div>
    </x-filament::card>
</x-filament::widget>
```

---

## Unified Dashboard Page

### Implementation

```php
class UnifiedBillingDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Billing Dashboard';
    protected static ?string $title = 'Unified Billing Analytics';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament-cashier::pages.unified-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-cashier.navigation.group', 'Billing');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TotalMrrWidget::class,
            TotalSubscribersWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        $widgets = [
            GatewayComparisonWidget::class,
            GatewayDistributionWidget::class,
        ];

        // Add per-gateway widgets dynamically
        foreach (Cashier::manager()->availableGateways() as $gateway) {
            $widgets[] = GatewayMetricsWidget::make(['gateway' => $gateway]);
        }

        return $widgets;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportReport')
                ->label('Export Full Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    DatePicker::make('start_date')->default(now()->subMonth()),
                    DatePicker::make('end_date')->default(now()),
                    CheckboxList::make('gateways')
                        ->options(fn () => $this->getGatewayOptions())
                        ->default(Cashier::manager()->availableGateways()->toArray()),
                    Select::make('format')
                        ->options(['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'])
                        ->default('csv'),
                ])
                ->action(fn (array $data) => $this->exportReport($data)),
        ];
    }
}
```

---

## Implementation Checklist

### Phase 1: Foundation
- [ ] Currency conversion utility
- [ ] Gateway detection helpers
- [ ] Color/emoji configuration

### Phase 2: Core Stats Widgets
- [ ] `TotalMrrWidget` with gateway breakdown
- [ ] `TotalSubscribersWidget` with counts
- [ ] Cross-gateway growth calculation

### Phase 3: Comparison Widgets
- [ ] `GatewayComparisonWidget` line chart
- [ ] `GatewayDistributionWidget` doughnut
- [ ] Currency normalization for comparison

### Phase 4: Per-Gateway Widgets
- [ ] `GatewayMetricsWidget` template
- [ ] Dynamic widget registration
- [ ] Top plans calculation

### Phase 5: Dashboard Page
- [ ] `UnifiedBillingDashboard` page
- [ ] Header/footer widget layout
- [ ] Export functionality
- [ ] Date range filtering

### Phase 6: Advanced Features
- [ ] Real-time polling
- [ ] Widget caching
- [ ] Performance optimization

---

## Navigation

**Previous:** [02-unified-subscriptions.md](02-unified-subscriptions.md)  
**Next:** [04-customer-portal.md](04-customer-portal.md)

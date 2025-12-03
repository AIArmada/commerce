# Cart Package Vision - Filament Enhancements

> **Document:** 09-filament-enhancements.md  
> **Series:** Cart Package Vision  
> **Focus:** Admin Dashboard, Real-time Monitoring, AI Assistant

---

## Table of Contents

1. [Cart Dashboard Overview](#1-cart-dashboard-overview)
2. [Real-time Cart Monitoring](#2-real-time-cart-monitoring)
3. [Cart Analytics Widgets](#3-cart-analytics-widgets)
4. [AI-Powered Admin Assistant](#4-ai-powered-admin-assistant)

---

## 1. Cart Dashboard Overview

### Vision Statement

Create a **comprehensive Filament dashboard** that provides real-time visibility into cart operations, analytics, and management capabilities.

### Dashboard Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    FILAMENT CART DASHBOARD                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    HEADER STATS                          │   │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐    │   │
│  │  │ Active  │  │ Today's │  │ Abandon │  │ Revenue │    │   │
│  │  │ Carts   │  │ Checkouts│  │  Rate   │  │ Pipeline│    │   │
│  │  │  247    │  │   89    │  │  23.4%  │  │ $12.4K  │    │   │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌───────────────────────────┐  ┌───────────────────────────┐  │
│  │     LIVE CART STREAM      │  │    CONVERSION FUNNEL      │  │
│  │  [Real-time updates]      │  │  Browse ─► Cart ─► Buy    │  │
│  │  • Cart created           │  │     100%    45%    12%    │  │
│  │  • Item added             │  │  ━━━━━━━━━━━━━━━━━━━━━━   │  │
│  │  • Checkout started       │  │  ████████████░░░░░░░░░░   │  │
│  │  • Payment completed      │  │                           │  │
│  └───────────────────────────┘  └───────────────────────────┘  │
│                                                                 │
│  ┌───────────────────────────┐  ┌───────────────────────────┐  │
│  │   ABANDONED CART LIST     │  │    AI INSIGHTS            │  │
│  │  [High value first]       │  │  • 15 carts at risk       │  │
│  │  ┌────────────────────┐   │  │  • Recovery potential:    │  │
│  │  │ $459 - 2h ago      │   │  │    $2,340 (Est.)          │  │
│  │  │ [View] [Recover]   │   │  │  • Recommended: Send      │  │
│  │  └────────────────────┘   │  │    recovery emails now    │  │
│  └───────────────────────────┘  └───────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Dashboard Implementation

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Pages;

use Filament\Pages\Dashboard;
use Filament\Widgets\WidgetConfiguration;

final class CartDashboard extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Commerce';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Cart Dashboard';
    protected static string $routePath = 'cart-dashboard';
    
    /**
     * @return array<class-string|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            CartStatsOverview::class,
            Widgets\CartFunnelWidget::make()->columnSpan('full'),
            Widgets\LiveCartStreamWidget::make()->columnSpan(1),
            Widgets\ConversionFunnelWidget::make()->columnSpan(1),
            Widgets\AbandonedCartsWidget::make()->columnSpan(1),
            Widgets\AIInsightsWidget::make()->columnSpan(1),
            Widgets\CartValueDistributionChart::make()->columnSpan('full'),
            Widgets\TopProductsInCartsWidget::make()->columnSpan(1),
            Widgets\CartConditionsBreakdownWidget::make()->columnSpan(1),
        ];
    }
    
    public function getColumns(): int|array
    {
        return 2;
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            CartStatsOverview::class,
        ];
    }
}
```

### Stats Overview Widget

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class CartStatsOverview extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '15s';
    
    protected function getStats(): array
    {
        $stats = app(CartAnalyticsService::class)->getDashboardStats();
        
        return [
            Stat::make('Active Carts', $stats->activeCarts)
                ->description($stats->activeCartsChange . '% from yesterday')
                ->descriptionIcon($stats->activeCartsChange >= 0 
                    ? 'heroicon-m-arrow-trending-up' 
                    : 'heroicon-m-arrow-trending-down')
                ->chart($stats->activeCartsHistory)
                ->color($stats->activeCartsChange >= 0 ? 'success' : 'danger'),
            
            Stat::make('Today\'s Checkouts', $stats->todayCheckouts)
                ->description('Conversion: ' . $stats->conversionRate . '%')
                ->chart($stats->checkoutsHistory)
                ->color('success'),
            
            Stat::make('Abandonment Rate', $stats->abandonmentRate . '%')
                ->description($stats->abandonmentChange . '% from avg')
                ->chart($stats->abandonmentHistory)
                ->color($stats->abandonmentRate > 25 ? 'danger' : 'warning'),
            
            Stat::make('Revenue Pipeline', money($stats->revenuePipeline))
                ->description($stats->pipelineCartsCount . ' carts')
                ->chart($stats->revenueHistory)
                ->color('primary'),
        ];
    }
}
```

---

## 2. Real-time Cart Monitoring

### Live Cart Stream Widget

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use Filament\Widgets\Widget;
use Livewire\Attributes\On;

final class LiveCartStreamWidget extends Widget
{
    protected static string $view = 'filament-cart::widgets.live-cart-stream';
    
    protected static ?string $pollingInterval = null; // Use WebSocket instead
    
    public array $events = [];
    public int $maxEvents = 50;
    
    public function mount(): void
    {
        $this->events = $this->getRecentEvents();
    }
    
    #[On('echo:cart-events,CartEvent')]
    public function handleCartEvent(array $event): void
    {
        array_unshift($this->events, $this->formatEvent($event));
        $this->events = array_slice($this->events, 0, $this->maxEvents);
    }
    
    private function getRecentEvents(): array
    {
        return DB::table('cart_events')
            ->orderByDesc('created_at')
            ->limit($this->maxEvents)
            ->get()
            ->map(fn($e) => $this->formatEvent((array) $e))
            ->toArray();
    }
    
    private function formatEvent(array $event): array
    {
        return [
            'id' => $event['id'],
            'type' => $event['type'],
            'icon' => $this->getEventIcon($event['type']),
            'color' => $this->getEventColor($event['type']),
            'message' => $this->getEventMessage($event),
            'time' => Carbon::parse($event['created_at'])->diffForHumans(),
            'cart_id' => $event['cart_id'],
        ];
    }
    
    private function getEventIcon(string $type): string
    {
        return match($type) {
            'CartCreated' => 'heroicon-o-plus-circle',
            'CartItemAdded' => 'heroicon-o-shopping-bag',
            'CartItemRemoved' => 'heroicon-o-minus-circle',
            'CartCheckoutStarted' => 'heroicon-o-credit-card',
            'CartCheckoutCompleted' => 'heroicon-o-check-circle',
            'CartAbandoned' => 'heroicon-o-x-circle',
            default => 'heroicon-o-information-circle',
        };
    }
    
    private function getEventColor(string $type): string
    {
        return match($type) {
            'CartCreated', 'CartItemAdded' => 'primary',
            'CartCheckoutCompleted' => 'success',
            'CartAbandoned' => 'danger',
            'CartCheckoutStarted' => 'warning',
            default => 'gray',
        };
    }
}
```

### Live Cart Stream Blade View

```blade
{{-- resources/views/widgets/live-cart-stream.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Live Cart Activity">
        <div 
            class="space-y-2 max-h-96 overflow-y-auto"
            wire:poll.5s
        >
            @forelse($events as $event)
                <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <div @class([
                        'p-2 rounded-full',
                        'bg-primary-100 text-primary-600' => $event['color'] === 'primary',
                        'bg-success-100 text-success-600' => $event['color'] === 'success',
                        'bg-danger-100 text-danger-600' => $event['color'] === 'danger',
                        'bg-warning-100 text-warning-600' => $event['color'] === 'warning',
                        'bg-gray-100 text-gray-600' => $event['color'] === 'gray',
                    ])>
                        <x-dynamic-component :component="$event['icon']" class="w-4 h-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            {{ $event['message'] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $event['time'] }}
                        </p>
                    </div>
                    <x-filament::link 
                        :href="route('filament.admin.resources.carts.view', $event['cart_id'])"
                        size="sm"
                    >
                        View
                    </x-filament::link>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500">
                    No recent cart activity
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## 3. Cart Analytics Widgets

### Conversion Funnel Widget

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use Filament\Widgets\ChartWidget;

final class ConversionFunnelWidget extends ChartWidget
{
    protected static ?string $heading = 'Conversion Funnel';
    protected static ?string $pollingInterval = '30s';
    
    protected function getData(): array
    {
        $data = app(CartAnalyticsService::class)->getConversionFunnel(
            period: now()->subDays(7),
        );
        
        return [
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => [
                        $data->visitors,
                        $data->cartCreated,
                        $data->itemsAdded,
                        $data->checkoutStarted,
                        $data->checkoutCompleted,
                    ],
                    'backgroundColor' => [
                        '#6366f1', // indigo
                        '#8b5cf6', // violet
                        '#a855f7', // purple
                        '#d946ef', // fuchsia
                        '#22c55e', // green
                    ],
                ],
            ],
            'labels' => [
                'Visitors',
                'Cart Created',
                'Items Added',
                'Checkout Started',
                'Completed',
            ],
        ];
    }
    
    protected function getType(): string
    {
        return 'funnel'; // Custom funnel chart type
    }
}
```

### Abandoned Carts Management Widget

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class AbandonedCartsWidget extends TableWidget
{
    protected static ?string $heading = 'Abandoned Carts (High Value)';
    protected int|string|array $columnSpan = 1;
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Cart::query()
                    ->whereNotNull('checkout_abandoned_at')
                    ->whereNull('checkout_completed_at')
                    ->where('recovery_attempts', '<', 3)
                    ->orderByDesc(DB::raw('
                        (SELECT COALESCE(SUM((item->>\'price\')::int * (item->>\'quantity\')::int), 0)
                         FROM jsonb_array_elements(items) AS item)
                    '))
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Customer')
                    ->description(fn(Cart $cart) => $cart->owner?->email ?? 'Guest')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Value')
                    ->money()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('checkout_abandoned_at')
                    ->label('Abandoned')
                    ->since()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('recovery_attempts')
                    ->label('Attempts')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn(Cart $cart) => CartResource::getUrl('view', ['record' => $cart]))
                    ->icon('heroicon-o-eye'),
                
                Tables\Actions\Action::make('recover')
                    ->action(function (Cart $cart) {
                        dispatch(new SendCartRecoveryEmail($cart));
                        $cart->increment('recovery_attempts');
                    })
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_recover')
                    ->action(function ($records) {
                        foreach ($records as $cart) {
                            dispatch(new SendCartRecoveryEmail($cart));
                            $cart->increment('recovery_attempts');
                        }
                    })
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation(),
            ]);
    }
}
```

### Cart Value Distribution Chart

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use Filament\Widgets\ChartWidget;

final class CartValueDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Cart Value Distribution';
    protected static ?string $pollingInterval = '60s';
    protected int|string|array $columnSpan = 'full';
    
    protected function getData(): array
    {
        $distribution = app(CartAnalyticsService::class)->getValueDistribution();
        
        return [
            'datasets' => [
                [
                    'label' => 'Carts',
                    'data' => $distribution->counts,
                    'backgroundColor' => '#6366f1',
                ],
            ],
            'labels' => $distribution->buckets,
        ];
    }
    
    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Carts',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Cart Value Range',
                    ],
                ],
            ],
        ];
    }
}
```

---

## 4. AI-Powered Admin Assistant

### AI Insights Widget

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use Filament\Widgets\Widget;

final class AIInsightsWidget extends Widget
{
    protected static string $view = 'filament-cart::widgets.ai-insights';
    protected static ?string $pollingInterval = '60s';
    
    public array $insights = [];
    
    public function mount(): void
    {
        $this->insights = $this->generateInsights();
    }
    
    public function refreshInsights(): void
    {
        $this->insights = $this->generateInsights();
    }
    
    private function generateInsights(): array
    {
        $analyzer = app(CartAIAnalyzer::class);
        
        return [
            $analyzer->getAbandonmentRiskInsight(),
            $analyzer->getRecoveryPotentialInsight(),
            $analyzer->getPeakTimesInsight(),
            $analyzer->getProductAffinityInsight(),
            $analyzer->getRecommendedActions(),
        ];
    }
}
```

### AI Insights Blade View

```blade
{{-- resources/views/widgets/ai-insights.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-sparkles class="w-5 h-5 text-purple-500" />
                AI Insights
            </div>
        </x-slot>
        
        <x-slot name="headerEnd">
            <x-filament::icon-button
                wire:click="refreshInsights"
                icon="heroicon-o-arrow-path"
                size="sm"
                label="Refresh insights"
            />
        </x-slot>
        
        <div class="space-y-4">
            @foreach($insights as $insight)
                <div @class([
                    'p-4 rounded-lg',
                    'bg-danger-50 border-l-4 border-danger-500' => $insight['severity'] === 'high',
                    'bg-warning-50 border-l-4 border-warning-500' => $insight['severity'] === 'medium',
                    'bg-success-50 border-l-4 border-success-500' => $insight['severity'] === 'low',
                    'bg-gray-50 border-l-4 border-gray-300' => $insight['severity'] === 'info',
                ])>
                    <div class="flex items-start gap-3">
                        <x-dynamic-component 
                            :component="$insight['icon']" 
                            @class([
                                'w-5 h-5 mt-0.5',
                                'text-danger-600' => $insight['severity'] === 'high',
                                'text-warning-600' => $insight['severity'] === 'medium',
                                'text-success-600' => $insight['severity'] === 'low',
                                'text-gray-600' => $insight['severity'] === 'info',
                            ])
                        />
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900">{{ $insight['title'] }}</h4>
                            <p class="text-sm text-gray-600 mt-1">{{ $insight['description'] }}</p>
                            
                            @if(isset($insight['action']))
                                <x-filament::button
                                    wire:click="{{ $insight['action']['method'] }}"
                                    size="sm"
                                    class="mt-3"
                                >
                                    {{ $insight['action']['label'] }}
                                </x-filament::button>
                            @endif
                            
                            @if(isset($insight['metrics']))
                                <div class="flex gap-4 mt-3">
                                    @foreach($insight['metrics'] as $metric)
                                        <div class="text-center">
                                            <div class="text-lg font-semibold">{{ $metric['value'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $metric['label'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

### Cart AI Analyzer Service

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

final class CartAIAnalyzer
{
    public function getAbandonmentRiskInsight(): array
    {
        $atRiskCarts = $this->getAtRiskCarts();
        $totalValue = $atRiskCarts->sum('total_value');
        
        if ($atRiskCarts->count() === 0) {
            return [
                'severity' => 'info',
                'icon' => 'heroicon-o-check-circle',
                'title' => 'No at-risk carts',
                'description' => 'All active carts are within normal engagement patterns.',
            ];
        }
        
        return [
            'severity' => $atRiskCarts->count() > 10 ? 'high' : 'medium',
            'icon' => 'heroicon-o-exclamation-triangle',
            'title' => "{$atRiskCarts->count()} carts at risk of abandonment",
            'description' => "Combined potential revenue: " . money($totalValue) . ". Consider proactive engagement.",
            'metrics' => [
                ['value' => $atRiskCarts->count(), 'label' => 'At Risk'],
                ['value' => money($totalValue), 'label' => 'Potential Revenue'],
                ['value' => $atRiskCarts->avg('minutes_inactive') . 'm', 'label' => 'Avg Inactive'],
            ],
            'action' => [
                'label' => 'View At-Risk Carts',
                'method' => 'viewAtRiskCarts',
            ],
        ];
    }
    
    public function getRecoveryPotentialInsight(): array
    {
        $recoverable = $this->getRecoverableCarts();
        $potentialRevenue = $recoverable->sum('total_value');
        $historicalRecoveryRate = $this->getHistoricalRecoveryRate();
        $expectedRecovery = $potentialRevenue * $historicalRecoveryRate;
        
        return [
            'severity' => $expectedRecovery > 1000 ? 'high' : 'medium',
            'icon' => 'heroicon-o-arrow-path',
            'title' => 'Recovery Potential: ' . money($expectedRecovery),
            'description' => "Based on historical {$historicalRecoveryRate}% recovery rate from {$recoverable->count()} abandoned carts.",
            'metrics' => [
                ['value' => $recoverable->count(), 'label' => 'Recoverable'],
                ['value' => money($potentialRevenue), 'label' => 'Total Value'],
                ['value' => round($historicalRecoveryRate * 100) . '%', 'label' => 'Recovery Rate'],
            ],
            'action' => [
                'label' => 'Send Recovery Emails',
                'method' => 'triggerRecoveryEmails',
            ],
        ];
    }
    
    public function getPeakTimesInsight(): array
    {
        $peakAnalysis = $this->analyzePeakTimes();
        
        return [
            'severity' => 'info',
            'icon' => 'heroicon-o-clock',
            'title' => "Peak activity: {$peakAnalysis->peakHour}:00 - {$peakAnalysis->peakHourEnd}:00",
            'description' => "{$peakAnalysis->peakPercentage}% of cart activity occurs during peak hours. Consider scheduling promotions accordingly.",
            'metrics' => [
                ['value' => $peakAnalysis->peakHour . ':00', 'label' => 'Peak Start'],
                ['value' => $peakAnalysis->avgCartsPerHour, 'label' => 'Avg Carts/Hr'],
                ['value' => $peakAnalysis->peakDay, 'label' => 'Peak Day'],
            ],
        ];
    }
    
    public function getProductAffinityInsight(): array
    {
        $affinities = $this->analyzeProductAffinities();
        
        return [
            'severity' => 'info',
            'icon' => 'heroicon-o-squares-plus',
            'title' => 'Top product combinations',
            'description' => "Products frequently bought together: {$affinities->top->productA} + {$affinities->top->productB} ({$affinities->top->frequency}% of carts)",
        ];
    }
    
    public function getRecommendedActions(): array
    {
        $actions = [];
        
        // Check for carts needing recovery
        $needsRecovery = $this->getCartsNeedingRecovery();
        if ($needsRecovery->count() > 0) {
            $actions[] = [
                'priority' => 'high',
                'action' => 'Send recovery emails',
                'impact' => "Could recover " . money($needsRecovery->sum('total_value') * 0.15),
            ];
        }
        
        // Check for high-value at-risk carts
        $highValueAtRisk = $this->getHighValueAtRiskCarts();
        if ($highValueAtRisk->count() > 0) {
            $actions[] = [
                'priority' => 'high',
                'action' => 'Personal outreach for ' . $highValueAtRisk->count() . ' high-value carts',
                'impact' => money($highValueAtRisk->sum('total_value')) . ' potential',
            ];
        }
        
        return [
            'severity' => 'info',
            'icon' => 'heroicon-o-light-bulb',
            'title' => 'Recommended Actions',
            'description' => count($actions) . ' actions recommended based on current data.',
            'actions_list' => $actions,
        ];
    }
}
```

### Cart Resource Enhancements

```php
<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources;

use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;
use Filament\Infolists;

final class CartResource extends Resource
{
    protected static ?string $model = Cart::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Commerce';
    
    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('instance')
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total')
                    ->money()
                    ->sortable()
                    ->color(fn($state) => $state > 10000 ? 'success' : null),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match($state) {
                        'active' => 'success',
                        'abandoned' => 'danger',
                        'completed' => 'gray',
                        default => 'warning',
                    }),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('instance')
                    ->options(fn() => Cart::distinct('instance')->pluck('instance', 'instance')),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'abandoned' => 'Abandoned',
                        'completed' => 'Completed',
                    ]),
                
                Tables\Filters\Filter::make('high_value')
                    ->query(fn($query) => $query->where('total_cents', '>', 10000))
                    ->label('High Value (>$100)'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('recover')
                    ->visible(fn(Cart $cart) => $cart->isAbandoned())
                    ->action(fn(Cart $cart) => dispatch(new SendCartRecoveryEmail($cart)))
                    ->icon('heroicon-o-envelope')
                    ->color('success'),
            ]);
    }
    
    public static function infolist(Infolists\Infolist $infolist): Infolists\Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Cart Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('identifier'),
                        Infolists\Components\TextEntry::make('instance')
                            ->badge(),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state) => match($state) {
                                'active' => 'success',
                                'abandoned' => 'danger',
                                'completed' => 'gray',
                                default => 'warning',
                            }),
                        Infolists\Components\TextEntry::make('total')
                            ->money(),
                    ])
                    ->columns(4),
                
                Infolists\Components\Section::make('Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->schema([
                                Infolists\Components\TextEntry::make('name'),
                                Infolists\Components\TextEntry::make('quantity'),
                                Infolists\Components\TextEntry::make('price')
                                    ->money(),
                                Infolists\Components\TextEntry::make('subtotal')
                                    ->money(),
                            ])
                            ->columns(4),
                    ]),
                
                Infolists\Components\Section::make('Conditions')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('conditions')
                            ->schema([
                                Infolists\Components\TextEntry::make('name'),
                                Infolists\Components\TextEntry::make('type')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('value'),
                            ])
                            ->columns(3),
                    ]),
                
                Infolists\Components\Section::make('Activity Timeline')
                    ->schema([
                        Infolists\Components\ViewEntry::make('events')
                            ->view('filament-cart::infolists.cart-timeline'),
                    ]),
            ]);
    }
}
```

---

## Summary: Filament Enhancement Priorities

| Enhancement | Complexity | Impact | Priority |
|-------------|------------|--------|----------|
| Stats Overview Widget | Low | High | **P0** |
| Cart Resource Table | Low | High | **P0** |
| Abandoned Carts Widget | Medium | High | **P0** |
| Live Cart Stream | Medium | Medium | **P1** |
| Conversion Funnel | Medium | Medium | **P1** |
| AI Insights Widget | High | High | **P2** |
| Cart Timeline View | Medium | Medium | **P2** |
| Product Affinity Analysis | High | Medium | **P3** |

---

**Next:** [10-implementation-roadmap.md](10-implementation-roadmap.md) - Prioritized Implementation Plan, Phase Details

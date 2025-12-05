# Filament Enhancements

> **Document:** 08 of 10  
> **Package:** `aiarmada/filament-chip`  
> **Status:** Vision

---

## Overview

Transform the Filament Chip panel into a **comprehensive payment management dashboard** with real-time analytics, subscription management, dispute handling, and intelligent insights.

---

## Dashboard Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    CHIP DASHBOARD                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐           │
│  │ Revenue │ │ Trans.  │ │ Success │ │  MRR    │           │
│  │ Today   │ │ Count   │ │  Rate   │ │         │           │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘           │
│                                                              │
│  ┌────────────────────────────┐ ┌────────────────────────┐  │
│  │     Revenue Chart          │ │   Payment Methods      │  │
│  │     (7/30/90 days)         │ │   Distribution         │  │
│  └────────────────────────────┘ └────────────────────────┘  │
│                                                              │
│  ┌────────────────────────────┐ ┌────────────────────────┐  │
│  │   Recent Transactions      │ │   Failure Analysis     │  │
│  │   (Live Updates)           │ │                        │  │
│  └────────────────────────────┘ └────────────────────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Dashboard Widgets

### Revenue Stats Widget

```php
class RevenueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '30s';
    
    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $analytics = app(ChipAnalyticsService::class);
        $metrics = $analytics->getDashboardMetrics($today, now());
        
        return [
            Stat::make('Revenue Today', Money::format($metrics->revenue->grossRevenue))
                ->description($this->getGrowthDescription($metrics->revenue->growthRate))
                ->descriptionIcon($metrics->revenue->growthRate >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($metrics->revenue->growthRate >= 0 ? 'success' : 'danger')
                ->chart($this->getRevenueSparkline()),
            
            Stat::make('Transactions', number_format($metrics->transactions->count))
                ->description('Successful payments')
                ->descriptionIcon('heroicon-m-credit-card'),
            
            Stat::make('Success Rate', $metrics->transactions->successRate . '%')
                ->description($metrics->transactions->failedCount . ' failed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($metrics->transactions->successRate >= 95 ? 'success' : 'warning'),
            
            Stat::make('MRR', Money::format($analytics->getMrr()))
                ->description('Monthly Recurring Revenue')
                ->descriptionIcon('heroicon-m-arrow-path'),
        ];
    }
}
```

### Revenue Chart Widget

```php
class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Overview';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 2;
    
    public ?string $filter = '7d';
    
    protected function getFilters(): ?array
    {
        return [
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '90d' => 'Last 90 days',
        ];
    }
    
    protected function getData(): array
    {
        $days = match ($this->filter) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
        
        $data = app(ChipAnalyticsService::class)
            ->getRevenueBreakdown(now()->subDays($days), now(), 'day');
        
        return [
            'datasets' => [
                [
                    'label' => 'Revenue (MYR)',
                    'data' => collect($data)->pluck('revenue')->map(fn ($v) => $v / 100),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Transactions',
                    'data' => collect($data)->pluck('count'),
                    'borderColor' => '#6366F1',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => collect($data)->pluck('period')->map(fn ($d) => Carbon::parse($d)->format('M j')),
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}
```

### Payment Methods Widget

```php
class PaymentMethodsWidget extends ChartWidget
{
    protected static ?string $heading = 'Payment Methods';
    protected static ?int $sort = 3;
    
    protected function getData(): array
    {
        $breakdown = app(PaymentMethodAnalyzer::class)
            ->getBreakdown(now()->subDays(30), now());
        
        return [
            'datasets' => [
                [
                    'data' => collect($breakdown)->pluck('revenue')->map(fn ($v) => $v / 100),
                    'backgroundColor' => [
                        '#10B981', // FPX
                        '#6366F1', // Card
                        '#F59E0B', // E-wallet
                        '#EF4444', // Other
                    ],
                ],
            ],
            'labels' => collect($breakdown)->pluck('method_name'),
        ];
    }
    
    protected function getType(): string
    {
        return 'doughnut';
    }
}
```

### Recent Transactions Widget

```php
class RecentTransactionsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Transactions';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '10s';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                ChipPurchase::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('chip_id')
                    ->label('ID')
                    ->limit(15)
                    ->copyable(),
                
                TextColumn::make('customer_email')
                    ->label('Customer')
                    ->searchable(),
                
                TextColumn::make('total_minor')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                
                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge(),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('created_at')
                    ->label('Time')
                    ->since(),
            ])
            ->actions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => PurchaseResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
```

---

## Enhanced Resources

### SubscriptionResource

```php
class SubscriptionResource extends Resource
{
    protected static ?string $model = ChipSubscription::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'Payments';
    protected static ?int $navigationSort = 2;
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subscriber.name')
                    ->label('Customer')
                    ->searchable(),
                
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable(),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (SubscriptionStatus $state): string => match ($state) {
                        SubscriptionStatus::Active => 'success',
                        SubscriptionStatus::Trialing => 'info',
                        SubscriptionStatus::PastDue => 'warning',
                        SubscriptionStatus::Canceled => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('current_period_end')
                    ->label('Renews')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('mrr')
                    ->label('MRR')
                    ->getStateUsing(fn ($record) => $record->calculateMrr())
                    ->money('MYR'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionStatus::class),
                
                SelectFilter::make('plan')
                    ->relationship('plan', 'name'),
                
                Filter::make('trial')
                    ->query(fn (Builder $query) => $query->whereNotNull('trial_ends_at')),
                
                Filter::make('expiring_soon')
                    ->query(fn (Builder $query) => $query
                        ->where('current_period_end', '<=', now()->addDays(7))
                        ->where('status', SubscriptionStatus::Active)),
            ])
            ->actions([
                Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(SubscriptionService::class)->cancel($record)),
                
                Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->visible(fn ($record) => $record->status === SubscriptionStatus::Active)
                    ->action(fn ($record) => app(SubscriptionService::class)->pause($record)),
                
                Action::make('resume')
                    ->icon('heroicon-o-play')
                    ->visible(fn ($record) => $record->status === SubscriptionStatus::Paused)
                    ->action(fn ($record) => app(SubscriptionService::class)->resume($record)),
            ])
            ->bulkActions([
                BulkAction::make('export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Collection $records) => Excel::download(
                        new SubscriptionsExport($records),
                        'subscriptions.xlsx'
                    )),
            ]);
    }
}
```

### DisputeResource

```php
class DisputeResource extends Resource
{
    protected static ?string $model = ChipDispute::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Payments';
    protected static ?int $navigationSort = 4;
    
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'open')->count();
    }
    
    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purchase.chip_id')
                    ->label('Transaction')
                    ->searchable(),
                
                TextColumn::make('reason')
                    ->badge(),
                
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->money('MYR'),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'under_review' => 'warning',
                        'won' => 'success',
                        'lost' => 'gray',
                        default => 'gray',
                    }),
                
                TextColumn::make('evidence_due_at')
                    ->label('Evidence Due')
                    ->date()
                    ->color(fn ($record) => $record->evidence_due_at?->isPast() ? 'danger' : null),
                
                TextColumn::make('created_at')
                    ->label('Opened')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'under_review' => 'Under Review',
                        'won' => 'Won',
                        'lost' => 'Lost',
                    ]),
                
                Filter::make('urgent')
                    ->query(fn (Builder $query) => $query
                        ->where('status', 'open')
                        ->where('evidence_due_at', '<=', now()->addDays(3))),
            ])
            ->actions([
                Action::make('submit_evidence')
                    ->icon('heroicon-o-document-plus')
                    ->visible(fn ($record) => $record->status === 'open')
                    ->form([
                        Select::make('type')
                            ->options(EvidenceType::class)
                            ->required(),
                        
                        FileUpload::make('file')
                            ->directory('dispute-evidence'),
                        
                        Textarea::make('description'),
                    ])
                    ->action(function ($record, array $data) {
                        app(DisputeEvidenceService::class)->submit($record, $data);
                    }),
                
                Action::make('accept_dispute')
                    ->icon('heroicon-o-check')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(DisputeService::class)->accept($record)),
            ]);
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Dispute Details')
                    ->schema([
                        TextEntry::make('reason')
                            ->badge(),
                        TextEntry::make('amount_minor')
                            ->label('Amount')
                            ->money('MYR'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('evidence_due_at')
                            ->date(),
                    ])
                    ->columns(4),
                
                Section::make('Customer Statement')
                    ->schema([
                        TextEntry::make('customer_statement')
                            ->prose(),
                    ]),
                
                Section::make('Evidence Submitted')
                    ->schema([
                        RepeatableEntry::make('evidence')
                            ->schema([
                                TextEntry::make('type')->badge(),
                                TextEntry::make('submitted_at')->dateTime(),
                                TextEntry::make('content')->prose(),
                            ]),
                    ]),
                
                Section::make('Transaction Details')
                    ->relationship('purchase')
                    ->schema([
                        TextEntry::make('chip_id'),
                        TextEntry::make('customer_email'),
                        TextEntry::make('payment_method'),
                        TextEntry::make('completed_at')->dateTime(),
                    ])
                    ->columns(4),
            ]);
    }
}
```

---

## Plan Management

### PlanResource

```php
class PlanResource extends Resource
{
    protected static ?string $model = ChipPlan::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Payments';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Plan Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),
                        
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        
                        Textarea::make('description'),
                    ])
                    ->columns(2),
                
                Section::make('Pricing')
                    ->schema([
                        TextInput::make('price_minor')
                            ->label('Price')
                            ->numeric()
                            ->prefix('MYR')
                            ->required()
                            ->helperText('Amount in cents'),
                        
                        Select::make('interval')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->required(),
                        
                        TextInput::make('interval_count')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                        
                        TextInput::make('trial_days')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(4),
                
                Section::make('Features')
                    ->schema([
                        KeyValue::make('features')
                            ->keyLabel('Feature')
                            ->valueLabel('Description'),
                    ]),
            ]);
    }
}
```

---

## Billing Template Builder

### BillingTemplateResource

```php
class BillingTemplateResource extends Resource
{
    protected static ?string $model = ChipBillingTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Payments';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in payment links'),
                        
                        Textarea::make('description'),
                    ]),
                
                Section::make('Payment Settings')
                    ->schema([
                        TextInput::make('default_amount_minor')
                            ->label('Default Amount')
                            ->numeric()
                            ->prefix('MYR')
                            ->nullable()
                            ->helperText('Leave empty for custom amounts'),
                        
                        TextInput::make('redirect_url')
                            ->url()
                            ->placeholder('https://example.com/thank-you'),
                        
                        Textarea::make('success_message')
                            ->placeholder('Thank you for your payment!'),
                    ]),
                
                Section::make('Custom Fields')
                    ->schema([
                        Repeater::make('custom_fields')
                            ->schema([
                                TextInput::make('name')
                                    ->required(),
                                
                                Select::make('type')
                                    ->options([
                                        'text' => 'Text',
                                        'email' => 'Email',
                                        'phone' => 'Phone',
                                        'number' => 'Number',
                                        'select' => 'Dropdown',
                                    ])
                                    ->required(),
                                
                                Toggle::make('required'),
                                
                                TextInput::make('options')
                                    ->helperText('Comma-separated for dropdowns'),
                            ])
                            ->columns(4),
                    ]),
                
                Section::make('Branding')
                    ->schema([
                        FileUpload::make('branding.logo')
                            ->image()
                            ->directory('billing-templates'),
                        
                        ColorPicker::make('branding.primary_color'),
                        
                        ColorPicker::make('branding.background_color'),
                    ])
                    ->columns(3),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                
                TextColumn::make('code')
                    ->copyable()
                    ->copyMessage('Link copied!'),
                
                TextColumn::make('usage_count')
                    ->label('Uses')
                    ->sortable(),
                
                TextColumn::make('total_collected_minor')
                    ->label('Collected')
                    ->money('MYR'),
                
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->actions([
                Action::make('copy_link')
                    ->icon('heroicon-o-link')
                    ->action(function ($record) {
                        Notification::make()
                            ->title('Link copied!')
                            ->body(route('chip.billing', $record->code))
                            ->success()
                            ->send();
                    }),
                
                Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('chip.billing', $record->code))
                    ->openUrlInNewTab(),
            ]);
    }
}
```

---

## Analytics Pages

### PaymentAnalyticsPage

```php
class PaymentAnalyticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Analytics';
    protected static string $view = 'filament-chip::pages.analytics';
    
    public string $period = '30d';
    
    public function getViewData(): array
    {
        $analytics = app(ChipAnalyticsService::class);
        $startDate = match ($this->period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };
        
        return [
            'metrics' => $analytics->getDashboardMetrics($startDate, now()),
            'revenueChart' => $analytics->getRevenueBreakdown($startDate, now()),
            'methodBreakdown' => app(PaymentMethodAnalyzer::class)->getBreakdown($startDate, now()),
            'failureAnalysis' => app(FailureAnalyzer::class)->analyze($startDate, now()),
            'cohorts' => app(CustomerCohortAnalyzer::class)->getRetentionCohorts($startDate, now()),
        ];
    }
}
```

---

## Webhook Monitor Page

### WebhookMonitorPage

```php
class WebhookMonitorPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'System';
    protected static string $view = 'filament-chip::pages.webhook-monitor';
    protected static ?string $pollingInterval = '5s';
    
    public function getViewData(): array
    {
        $monitor = app(WebhookMonitor::class);
        
        return [
            'health' => $monitor->getHealth(),
            'eventDistribution' => $monitor->getEventDistribution(now()->subDay()),
            'failedWebhooks' => $monitor->getFailedWebhooks(),
            'recentLogs' => ChipWebhookLog::latest()->limit(50)->get(),
        ];
    }
    
    public function retryWebhook(string $id): void
    {
        $log = ChipWebhookLog::findOrFail($id);
        app(WebhookRetryManager::class)->retry($log);
        
        Notification::make()
            ->title('Webhook retry initiated')
            ->success()
            ->send();
    }
}
```

---

## Navigation Structure

```
Payments
├── Dashboard (widgets)
├── Transactions (PurchaseResource)
├── Subscriptions (SubscriptionResource)
├── Plans (PlanResource)
├── Billing Templates (BillingTemplateResource)
├── Disputes (DisputeResource)
├── Payouts (PayoutResource)
└── Recurring Tokens (RecurringTokenResource)

Analytics
├── Payment Analytics
├── Subscription Metrics
└── Revenue Reports

System
├── Webhook Monitor
└── Settings
```

---

## Navigation

**Previous:** [07-database-evolution.md](07-database-evolution.md)  
**Next:** [09-implementation-roadmap.md](09-implementation-roadmap.md)

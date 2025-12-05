# Filament Enhancements

> **Document:** 09-filament-enhancements.md  
> **Status:** Vision  
> **Priority:** P2

---

## Overview

The `filament-vouchers` package should evolve from basic CRUD to a **comprehensive promotional management console** with visual campaign builders, A/B test dashboards, and real-time analytics.

---

## Current State

The Filament package likely provides:
- Voucher resource (CRUD)
- Basic listing and filtering
- Form for voucher creation

---

## Vision: Promotional Command Center

### 9.1 Dashboard

```php
class VoucherDashboard extends Page
{
    protected static string $view = 'filament-vouchers::pages.dashboard';
    
    public function getWidgets(): array
    {
        return [
            VoucherStatsOverview::class,
            ActiveCampaignsWidget::class,
            TopPerformingVouchersWidget::class,
            RedemptionTrendChart::class,
            AbandonmentRecoveryWidget::class,
            FraudAlertsWidget::class,
        ];
    }
}
```

### Stats Overview Widget

```php
class VoucherStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Vouchers', Voucher::active()->count())
                ->description('Currently usable')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('success'),
                
            Stat::make('Redemptions Today', VoucherUsage::today()->count())
                ->description($this->getRedemptionTrend())
                ->descriptionIcon($this->getTrendIcon())
                ->color($this->getTrendColor()),
                
            Stat::make('Total Discount Given', Money::MYR($this->getTotalDiscount())->format())
                ->description('This month')
                ->color('warning'),
                
            Stat::make('Conversion Rate', $this->getConversionRate() . '%')
                ->description('Applied → Converted')
                ->color('info'),
        ];
    }
}
```

---

### 9.2 Campaign Builder

```php
class CampaignResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            Wizard::make([
                // Step 1: Campaign Basics
                Wizard\Step::make('Campaign Details')
                    ->schema([
                        TextInput::make('name')->required(),
                        Select::make('type')
                            ->options(CampaignType::options())
                            ->required(),
                        Select::make('objective')
                            ->options(CampaignObjective::options()),
                        RichEditor::make('description'),
                    ]),
                
                // Step 2: Schedule
                Wizard\Step::make('Schedule')
                    ->schema([
                        DateTimePicker::make('starts_at')->required(),
                        DateTimePicker::make('ends_at')->required(),
                        Select::make('timezone')
                            ->options(Timezone::options())
                            ->default('Asia/Kuala_Lumpur'),
                    ]),
                
                // Step 3: Budget
                Wizard\Step::make('Budget & Limits')
                    ->schema([
                        MoneyInput::make('budget_cents')
                            ->label('Campaign Budget'),
                        TextInput::make('max_redemptions')
                            ->numeric()
                            ->label('Maximum Redemptions'),
                    ]),
                
                // Step 4: Variants (A/B Testing)
                Wizard\Step::make('Variants')
                    ->schema([
                        Toggle::make('ab_testing_enabled')
                            ->label('Enable A/B Testing')
                            ->reactive(),
                        
                        Repeater::make('variants')
                            ->schema([
                                TextInput::make('name')->required(),
                                Select::make('voucher_id')
                                    ->relationship('voucher', 'code')
                                    ->searchable()
                                    ->required(),
                                Slider::make('traffic_percentage')
                                    ->min(0)
                                    ->max(100)
                                    ->step(5)
                                    ->default(50),
                                Toggle::make('is_control')
                                    ->label('Control Group'),
                            ])
                            ->columns(4)
                            ->visible(fn (Get $get) => $get('ab_testing_enabled')),
                    ]),
                
                // Step 5: Automation
                Wizard\Step::make('Automation')
                    ->schema([
                        Select::make('automation_trigger')
                            ->options(CampaignTrigger::options())
                            ->nullable(),
                        
                        KeyValue::make('automation_conditions')
                            ->visible(fn (Get $get) => $get('automation_trigger')),
                        
                        TextInput::make('automation_delay')
                            ->placeholder('e.g., 1 hour, 30 minutes'),
                    ]),
            ])->columnSpanFull(),
        ]);
    }
}
```

---

### 9.3 A/B Test Dashboard

```php
class ABTestDashboard extends Page
{
    public Campaign $campaign;
    
    protected function getViewData(): array
    {
        $analyzer = app(ABTestAnalyzer::class);
        $result = $analyzer->analyze($this->campaign);
        
        return [
            'result' => $result,
            'chartData' => $this->getChartData(),
        ];
    }
}
```

**Dashboard Components:**

```blade
{{-- Variant Comparison Cards --}}
<div class="grid grid-cols-2 gap-4">
    @foreach($result->variants as $code => $data)
        <x-filament::card>
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold">Variant {{ $code }}</h3>
                @if($data['variant']->is_control)
                    <x-filament::badge color="gray">Control</x-filament::badge>
                @endif
                @if($code === $result->suggestedWinner)
                    <x-filament::badge color="success">Winner</x-filament::badge>
                @endif
            </div>
            
            <div class="mt-4 grid grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Applications</p>
                    <p class="text-2xl font-bold">{{ number_format($data['sample_size']) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Conversion Rate</p>
                    <p class="text-2xl font-bold">{{ number_format($data['conversion_rate'] * 100, 2) }}%</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Revenue</p>
                    <p class="text-2xl font-bold">{{ Money::MYR($data['variant']->revenue_cents)->format() }}</p>
                </div>
            </div>
            
            @if(isset($data['lift']))
                <div class="mt-4 p-3 bg-gray-50 rounded">
                    <p class="text-sm">
                        <span class="{{ $data['lift'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $data['lift'] > 0 ? '+' : '' }}{{ number_format($data['lift'] * 100, 1) }}%
                        </span>
                        lift vs control
                        @if($data['significance'] >= 95)
                            <x-filament::badge color="success" size="sm">
                                {{ number_format($data['significance'], 1) }}% significant
                            </x-filament::badge>
                        @endif
                    </p>
                </div>
            @endif
        </x-filament::card>
    @endforeach
</div>

{{-- Conversion Over Time Chart --}}
<x-filament::card class="mt-6">
    <h3 class="text-lg font-bold mb-4">Conversion Rate Over Time</h3>
    <div wire:ignore>
        <canvas id="conversionChart"></canvas>
    </div>
</x-filament::card>
```

---

### 9.4 Targeting Rule Builder

```php
class TargetingRuleBuilder extends Field
{
    protected string $view = 'filament-vouchers::components.targeting-rule-builder';
    
    public function getSchema(): array
    {
        return [
            Select::make('mode')
                ->options([
                    'all' => 'ALL rules must match',
                    'any' => 'ANY rule must match',
                    'custom' => 'Custom expression',
                ])
                ->default('all')
                ->reactive(),
            
            Repeater::make('rules')
                ->schema([
                    Select::make('type')
                        ->options(TargetingRuleType::options())
                        ->reactive()
                        ->required(),
                    
                    Select::make('operator')
                        ->options(fn (Get $get) => $this->getOperatorsFor($get('type')))
                        ->required(),
                    
                    $this->getDynamicValueField(),
                ])
                ->collapsible()
                ->itemLabel(fn (array $state) => $this->formatRuleLabel($state))
                ->addActionLabel('Add Targeting Rule'),
        ];
    }
    
    private function getDynamicValueField(): Component
    {
        return Grid::make(2)->schema([
            TagsInput::make('values')
                ->label('Values')
                ->visible(fn (Get $get) => $this->requiresMultipleValues($get('type'))),
            
            TextInput::make('value')
                ->numeric()
                ->visible(fn (Get $get) => $this->requiresSingleValue($get('type'))),
            
            TimePicker::make('start_time')
                ->visible(fn (Get $get) => $get('type') === 'time_window'),
            
            TimePicker::make('end_time')
                ->visible(fn (Get $get) => $get('type') === 'time_window'),
        ]);
    }
}
```

---

### 9.5 Gift Card Management

```php
class GiftCardResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->copyable(),
                    
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'standard' => 'gray',
                        'promotional' => 'success',
                        'corporate' => 'info',
                        default => 'gray',
                    }),
                    
                TextColumn::make('current_balance')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),
                    
                TextColumn::make('initial_balance')
                    ->money('MYR', divideBy: 100)
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'expired' => 'danger',
                        'depleted' => 'warning',
                        default => 'gray',
                    }),
                    
                TextColumn::make('recipient.name')
                    ->label('Owner'),
                    
                TextColumn::make('expires_at')
                    ->date()
                    ->sortable(),
            ])
            ->actions([
                Action::make('topUp')
                    ->icon('heroicon-o-plus-circle')
                    ->form([
                        MoneyInput::make('amount')
                            ->required(),
                        Textarea::make('notes'),
                    ])
                    ->action(fn (GiftCard $record, array $data) => 
                        $record->topUp($data['amount'], auth()->user())
                    ),
                    
                Action::make('viewTransactions')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (GiftCard $record) => 
                        GiftCardTransactionResource::getUrl('index', [
                            'tableFilters' => ['gift_card_id' => $record->id]
                        ])
                    ),
            ])
            ->bulkActions([
                BulkAction::make('activateBulk')
                    ->icon('heroicon-o-check-circle')
                    ->action(fn (Collection $records) => 
                        $records->each->activate()
                    ),
                    
                ExportBulkAction::make()
                    ->exporter(GiftCardExporter::class),
            ]);
    }
}
```

### Bulk Gift Card Issuance

```php
class BulkIssueGiftCards extends Page
{
    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Gift Card Configuration')
                ->schema([
                    Select::make('type')
                        ->options(GiftCardType::options())
                        ->required(),
                    
                    MoneyInput::make('value')
                        ->label('Card Value')
                        ->required(),
                    
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(1000),
                    
                    DatePicker::make('expires_at')
                        ->label('Expiry Date'),
                ]),
            
            Section::make('Code Generation')
                ->schema([
                    Select::make('code_format')
                        ->options([
                            'random' => 'Random (GC-XXXX-XXXX)',
                            'sequential' => 'Sequential (GC-0001, GC-0002...)',
                            'custom_prefix' => 'Custom Prefix',
                        ])
                        ->default('random'),
                    
                    TextInput::make('custom_prefix')
                        ->visible(fn (Get $get) => $get('code_format') === 'custom_prefix'),
                ]),
            
            Section::make('Recipient Assignment')
                ->schema([
                    Toggle::make('assign_to_recipients')
                        ->reactive(),
                    
                    FileUpload::make('recipients_csv')
                        ->label('Upload Recipients CSV')
                        ->visible(fn (Get $get) => $get('assign_to_recipients'))
                        ->acceptedFileTypes(['text/csv']),
                ]),
        ]);
    }
    
    public function issue(): void
    {
        $data = $this->form->getState();
        
        $job = new BulkIssueGiftCardsJob($data, auth()->user());
        
        dispatch($job);
        
        Notification::make()
            ->title('Gift cards are being generated')
            ->body("You'll receive a notification when the {$data['quantity']} cards are ready.")
            ->success()
            ->send();
    }
}
```

---

### 9.6 Fraud Monitoring

```php
class FraudMonitoringWidget extends Widget
{
    protected static string $view = 'filament-vouchers::widgets.fraud-monitoring';
    
    public function getAlerts(): Collection
    {
        return VoucherFraudSignal::query()
            ->where('created_at', '>=', now()->subDay())
            ->where('severity_score', '>=', 0.7)
            ->orderByDesc('severity_score')
            ->limit(10)
            ->get();
    }
}
```

```blade
{{-- Fraud Alert Card --}}
<x-filament::card>
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-bold text-red-600">
            <x-heroicon-o-shield-exclamation class="w-5 h-5 inline" />
            Fraud Alerts ({{ $alerts->count() }})
        </h3>
        <x-filament::link href="{{ route('filament.vouchers.fraud-signals') }}">
            View All
        </x-filament::link>
    </div>
    
    <div class="mt-4 space-y-3">
        @foreach($alerts as $alert)
            <div class="p-3 bg-red-50 rounded border border-red-200">
                <div class="flex justify-between">
                    <span class="font-medium">{{ $alert->signal_type }}</span>
                    <span class="text-sm text-gray-500">
                        {{ $alert->created_at->diffForHumans() }}
                    </span>
                </div>
                <p class="text-sm text-gray-600 mt-1">
                    Voucher: {{ $alert->voucher->code }}
                </p>
                <div class="mt-2 flex gap-2">
                    @if($alert->was_blocked)
                        <x-filament::badge color="success" size="sm">Blocked</x-filament::badge>
                    @else
                        <x-filament::button size="xs" color="danger">
                            Block Voucher
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-filament::card>
```

---

## Implementation Phases

### Phase 1: Dashboard Foundation
- [ ] Stats overview widget
- [ ] Active campaigns widget
- [ ] Redemption trend chart

### Phase 2: Campaign Builder
- [ ] Campaign resource with wizard
- [ ] Variant management
- [ ] Schedule configuration

### Phase 3: A/B Testing UI
- [ ] Test dashboard page
- [ ] Variant comparison cards
- [ ] Statistical significance display

### Phase 4: Advanced Components
- [ ] Targeting rule builder
- [ ] Gift card resource
- [ ] Bulk issuance tool

### Phase 5: Monitoring
- [ ] Fraud alerts widget
- [ ] Real-time redemption feed
- [ ] Performance alerts

---

## Navigation

**Previous:** [08-database-evolution.md](08-database-evolution.md)  
**Next:** [10-implementation-roadmap.md](10-implementation-roadmap.md)

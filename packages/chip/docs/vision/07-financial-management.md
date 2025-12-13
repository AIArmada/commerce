---
title: Financial Management
---

# Financial Management (Phase 6)

> **Document:** 07 of 09  
> **Package:** `aiarmada/filament-chip`  
> **Status:** Vision  
> **Priority:** P1 - High

---

## Overview

Build comprehensive financial management features including **Company Statements**, **Live Account Balance**, and **Account Turnover** widgets. These features provide real-time visibility into CHIP account status.

---

## API Foundation (Already Implemented in chip)

```php
// Account Balance & Turnover (LIVE from CHIP API)
ChipCollectService::getAccountBalance()
ChipCollectService::getAccountTurnover($filters)

// Company Statements
ChipCollectService::listCompanyStatements($filters)
ChipCollectService::getCompanyStatement($id)
ChipCollectService::cancelCompanyStatement($id)
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  FINANCIAL MANAGEMENT                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              FinancialDashboard Widgets                │  │
│  │  ┌──────────────────┐  ┌──────────────────────────┐   │  │
│  │  │AccountBalance    │  │AccountTurnover           │   │  │
│  │  │Widget (LIVE API) │  │Widget (LIVE API)         │   │  │
│  │  │                  │  │                          │   │  │
│  │  │- Available       │  │- Total Inflow            │   │  │
│  │  │- Pending         │  │- Total Outflow           │   │  │
│  │  │- Reserved        │  │- Net Flow                │   │  │
│  │  │- Refresh Button  │  │- Date Range Selector     │   │  │
│  │  └──────────────────┘  └──────────────────────────┘   │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │            CompanyStatementResource                    │  │
│  │                                                        │  │
│  │  - List all statements                                │  │
│  │  - View statement details                             │  │
│  │  - Download PDF                                       │  │
│  │  - Cancel pending statements                          │  │
│  │  - Track generation status                            │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## AccountBalanceWidget (Live API)

### Design

```php
class AccountBalanceWidget extends Widget
{
    protected static string $view = 'filament-chip::widgets.account-balance';
    
    public array $balance = [];
    public bool $isLoading = false;
    public ?string $lastUpdated = null;
    
    public function mount(): void
    {
        $this->loadBalance();
    }
    
    public function loadBalance(): void
    {
        $this->isLoading = true;
        
        try {
            $this->balance = app(ChipCollectService::class)->getAccountBalance();
            $this->lastUpdated = now()->format('H:i:s');
        } catch (Throwable $e) {
            Notification::make()
                ->title('Failed to load balance')
                ->danger()
                ->send();
        }
        
        $this->isLoading = false;
    }
    
    public function refresh(): void
    {
        $this->loadBalance();
    }
}
```

### View Template

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Account Balance</span>
                <x-filament::badge color="info">
                    LIVE
                </x-filament::badge>
            </div>
        </x-slot>
        
        <x-slot name="headerEnd">
            <x-filament::icon-button
                icon="heroicon-o-arrow-path"
                wire:click="refresh"
                wire:loading.attr="disabled"
                label="Refresh"
            />
        </x-slot>
        
        <div class="grid grid-cols-3 gap-4">
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Available</p>
                <p class="text-2xl font-bold text-success-600">
                    RM {{ number_format($balance['available'] ?? 0, 2) }}
                </p>
            </div>
            
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Pending</p>
                <p class="text-2xl font-bold text-warning-600">
                    RM {{ number_format($balance['pending'] ?? 0, 2) }}
                </p>
            </div>
            
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Reserved</p>
                <p class="text-2xl font-bold text-gray-600">
                    RM {{ number_format($balance['reserved'] ?? 0, 2) }}
                </p>
            </div>
        </div>
        
        @if($lastUpdated)
            <p class="text-xs text-gray-400 mt-4 text-right">
                Last updated: {{ $lastUpdated }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## AccountTurnoverWidget (Live API)

### Design

```php
class AccountTurnoverWidget extends Widget
{
    protected static string $view = 'filament-chip::widgets.account-turnover';
    
    public string $period = '30';
    public array $turnover = [];
    public ?string $lastUpdated = null;
    
    public function mount(): void
    {
        $this->loadTurnover();
    }
    
    public function loadTurnover(): void
    {
        $endDate = now();
        $startDate = now()->subDays((int) $this->period);
        
        try {
            $this->turnover = app(ChipCollectService::class)->getAccountTurnover([
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);
            $this->lastUpdated = now()->format('H:i:s');
        } catch (Throwable $e) {
            Notification::make()
                ->title('Failed to load turnover')
                ->danger()
                ->send();
        }
    }
    
    public function updatedPeriod(): void
    {
        $this->loadTurnover();
    }
}
```

### View Template

```blade
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>Account Turnover</span>
                <x-filament::badge color="info">
                    LIVE
                </x-filament::badge>
            </div>
        </x-slot>
        
        <x-slot name="headerEnd">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="period">
                    <option value="7">Last 7 Days</option>
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </x-slot>
        
        <div class="grid grid-cols-3 gap-4">
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Inflow</p>
                <p class="text-2xl font-bold text-success-600">
                    RM {{ number_format($turnover['inflow'] ?? 0, 2) }}
                </p>
            </div>
            
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Outflow</p>
                <p class="text-2xl font-bold text-danger-600">
                    RM {{ number_format($turnover['outflow'] ?? 0, 2) }}
                </p>
            </div>
            
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Net Flow</p>
                @php
                    $netFlow = ($turnover['inflow'] ?? 0) - ($turnover['outflow'] ?? 0);
                @endphp
                <p class="text-2xl font-bold {{ $netFlow >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    RM {{ number_format(abs($netFlow), 2) }}
                    {{ $netFlow >= 0 ? '↑' : '↓' }}
                </p>
            </div>
        </div>
        
        @if($lastUpdated)
            <p class="text-xs text-gray-400 mt-4 text-right">
                Last updated: {{ $lastUpdated }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

---

## CompanyStatementResource

### Database Model (Existing)

```php
// AIArmada\Chip\Models\CompanyStatement
@property string|null $status
@property bool $is_test
@property int|null $created_on
@property int|null $updated_on
@property int|null $began_on
@property int|null $finished_on
```

### Table Schema

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('id')
                ->label('Statement ID')
                ->searchable()
                ->sortable(),
                
            TextColumn::make('status')
                ->badge()
                ->color(fn (?string $state) => match ($state) {
                    'completed', 'ready' => 'success',
                    'queued', 'processing' => 'warning',
                    'failed', 'expired' => 'danger',
                    default => 'gray',
                }),
                
            IconColumn::make('is_test')
                ->label('Test Mode')
                ->boolean()
                ->toggleable(),
                
            TextColumn::make('began_on')
                ->label('Period Start')
                ->dateTime()
                ->sortable(),
                
            TextColumn::make('finished_on')
                ->label('Period End')
                ->dateTime()
                ->sortable(),
                
            TextColumn::make('created_on')
                ->label('Created')
                ->dateTime()
                ->sortable(),
        ])
        ->filters([
            SelectFilter::make('status')
                ->options([
                    'queued' => 'Queued',
                    'processing' => 'Processing',
                    'completed' => 'Completed',
                    'ready' => 'Ready',
                    'failed' => 'Failed',
                    'expired' => 'Expired',
                ]),
                
            TernaryFilter::make('is_test')
                ->label('Test Mode'),
        ])
        ->actions([
            ViewAction::make(),
            
            Action::make('download')
                ->icon(Heroicon::ArrowDownTray)
                ->color('success')
                ->visible(fn (CompanyStatement $record) => in_array($record->status, ['completed', 'ready']))
                ->url(fn (CompanyStatement $record) => $record->download_url)
                ->openUrlInNewTab(),
                
            Action::make('cancel')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (CompanyStatement $record) => in_array($record->status, ['queued', 'processing']))
                ->action(fn (CompanyStatement $record) => app(ChipCollectService::class)->cancelCompanyStatement($record->id)),
        ])
        ->headerActions([
            Action::make('generate')
                ->label('Generate Statement')
                ->icon(Heroicon::DocumentPlus)
                ->form([
                    DatePicker::make('start_date')
                        ->label('Period Start')
                        ->required()
                        ->maxDate(now()),
                        
                    DatePicker::make('end_date')
                        ->label('Period End')
                        ->required()
                        ->maxDate(now())
                        ->afterOrEqual('start_date'),
                ])
                ->action(function (array $data): void {
                    // Generate statement via API
                    // Implementation depends on CHIP API method
                }),
        ]);
}
```

### Infolist Schema

```php
public static function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            Section::make('Statement Details')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (?string $state) => CompanyStatement::statusColor($state)),
                    IconEntry::make('is_test')
                        ->label('Test Mode')
                        ->boolean(),
                ]),
                
            Section::make('Period')
                ->schema([
                    TextEntry::make('began_on')
                        ->label('Start Date')
                        ->dateTime(),
                    TextEntry::make('finished_on')
                        ->label('End Date')
                        ->dateTime(),
                ]),
                
            Section::make('Timestamps')
                ->schema([
                    TextEntry::make('created_on')
                        ->label('Created')
                        ->dateTime(),
                    TextEntry::make('updated_on')
                        ->label('Last Updated')
                        ->dateTime(),
                ]),
        ]);
}
```

---

## FinancialOverviewPage

### Page Structure

```php
class FinancialOverviewPage extends Page
{
    protected static string $view = 'filament-chip::pages.financial-overview';
    
    protected static ?string $navigationIcon = Heroicon::OutlinedBuildingLibrary;
    protected static ?string $navigationLabel = 'Financial Overview';
    protected static ?string $title = 'Financial Overview';
    protected static ?string $slug = 'chip/financial';
    protected static ?int $navigationSort = 102;
    
    public function getHeaderWidgets(): array
    {
        return [
            AccountBalanceWidget::class,
            AccountTurnoverWidget::class,
        ];
    }
}
```

---

## Key Differences from Local Analytics

| Aspect | Local Analytics (Phase 3) | Financial Management (Phase 6) |
|--------|--------------------------|-------------------------------|
| Data Source | Local `chip_purchases` table | **LIVE CHIP API** |
| Update Frequency | Aggregated daily | Real-time on demand |
| Metrics | Revenue, success rates, trends | Balance, turnover, statements |
| Latency | Instant (local DB) | Network dependent (API call) |
| Use Case | Historical analysis | Current financial status |

---

## Success Criteria

- [ ] AccountBalanceWidget shows live balance from CHIP API
- [ ] AccountTurnoverWidget shows live turnover with date range
- [ ] CompanyStatementResource with list/view/download
- [ ] Cancel pending statements action works
- [ ] Generate statement form works
- [ ] Live badges indicate real-time data
- [ ] Refresh buttons work correctly
- [ ] PHPStan Level 6 compliance

---

## Navigation

**Previous:** [06-chip-send-admin.md](06-chip-send-admin.md)  
**Next:** [08-token-webhook-management.md](08-token-webhook-management.md)


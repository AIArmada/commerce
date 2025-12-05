# Filament Enhancements

> **Document:** 8 of 9  
> **Package:** `aiarmada/filament-shipping`  
> **Status:** Vision

---

## Overview

Transform the Filament Shipping panel into a **comprehensive shipping operations center** with rate comparison, carrier management, shipment creation, returns handling, and performance analytics.

---

## Dashboard Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                  SHIPPING DASHBOARD                           │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐            │
│  │ Today's │ │ In      │ │Pending  │ │  Carrier│            │
│  │ Shipped │ │ Transit │ │ Issues  │ │  Health │            │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘            │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │           Shipment Volume (Last 30 Days)               │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                               │
│  ┌───────────────────────┐  ┌───────────────────────────┐   │
│  │ Recent Shipments      │  │ Carrier Performance       │   │
│  │ (Pending Actions)     │  │ (Delivery Rates)          │   │
│  └───────────────────────┘  └───────────────────────────┘   │
│                                                               │
│  ┌───────────────────────┐  ┌───────────────────────────┐   │
│  │ Exception Alerts      │  │ Pending Returns           │   │
│  └───────────────────────┘  └───────────────────────────┘   │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## Dashboard Widgets

### ShippingOverviewWidget

```php
class ShippingOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '60s';
    
    protected function getStats(): array
    {
        return [
            Stat::make("Today's Shipments", $this->getTodayCount())
                ->description($this->getTodayGrowth())
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($this->getWeeklyTrend()),
            
            Stat::make('In Transit', $this->getInTransitCount())
                ->description("{$this->getOutForDelivery()} out for delivery")
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary'),
            
            Stat::make('Pending Issues', $this->getIssueCount())
                ->description($this->getUrgentIssues() . ' urgent')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->url(ShipmentResource::getUrl('index', [
                    'tableFilters[has_issues][value]' => true,
                ])),
            
            Stat::make('Carrier Health', $this->getCarrierHealth())
                ->description($this->getWorstCarrier())
                ->descriptionIcon('heroicon-m-heart')
                ->color($this->getHealthColor()),
        ];
    }

    private function getTodayCount(): int
    {
        return Shipment::whereDate('created_at', today())->count();
    }

    private function getWeeklyTrend(): array
    {
        return Shipment::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->pluck('count')
            ->toArray();
    }
}
```

### CarrierPerformanceWidget

```php
class CarrierPerformanceWidget extends ChartWidget
{
    protected static ?string $heading = 'Carrier Performance';
    protected int|string|array $columnSpan = 1;
    
    protected function getData(): array
    {
        $metrics = CarrierMetrics::query()
            ->where('period_start', '>=', now()->subDays(30))
            ->selectRaw('carrier_id, AVG(delivery_success_rate) as success_rate, AVG(on_time_rate) as on_time')
            ->groupBy('carrier_id')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Delivery Success %',
                    'data' => $metrics->pluck('success_rate')->map(fn ($v) => $v * 100)->toArray(),
                    'backgroundColor' => '#3B82F6',
                ],
                [
                    'label' => 'On-Time %',
                    'data' => $metrics->pluck('on_time')->map(fn ($v) => $v * 100)->toArray(),
                    'backgroundColor' => '#10B981',
                ],
            ],
            'labels' => $metrics->pluck('carrier_id')->map(fn ($id) => 
                Carrier::find($id)?->name ?? $id
            )->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
```

### ExceptionAlertsWidget

```php
class ExceptionAlertsWidget extends Widget
{
    protected static string $view = 'filament-shipping::widgets.exception-alerts';
    protected int|string|array $columnSpan = 1;
    protected static ?string $pollingInterval = '120s';
    
    public function getExceptions(): Collection
    {
        return Shipment::query()
            ->whereIn('tracking_status', [
                'exception',
                'delayed',
                'address_issue',
                'delivery_attempted',
            ])
            ->with('latestTrackingEvent')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($shipment) => [
                'tracking_number' => $shipment->tracking_number,
                'carrier' => $shipment->carrier_id,
                'status' => $shipment->tracking_status,
                'last_update' => $shipment->latestTrackingEvent?->occurred_at,
                'location' => $shipment->latestTrackingEvent?->getLocationString(),
                'order_number' => $shipment->order?->order_number,
            ]);
    }
}
```

---

## Shipment Resource

### ShipmentResource

```php
class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Shipping';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Shipment Details')
                    ->schema([
                        Select::make('carrier_id')
                            ->label('Carrier')
                            ->options(Carrier::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->live(),
                        
                        Select::make('service_type')
                            ->options(function ($get) {
                                $carrier = Carrier::find($get('carrier_id'));
                                return $carrier?->getServiceTypes() ?? [];
                            })
                            ->required(),
                        
                        TextInput::make('reference')
                            ->label('Reference / Order #'),
                    ])
                    ->columns(3),
                
                Section::make('Sender Address')
                    ->schema([
                        TextInput::make('sender_name')->required(),
                        TextInput::make('sender_phone')->required(),
                        TextInput::make('sender_address_line1')->required(),
                        TextInput::make('sender_address_line2'),
                        Grid::make(3)->schema([
                            TextInput::make('sender_city'),
                            TextInput::make('sender_state')->required(),
                            TextInput::make('sender_postcode')->required(),
                        ]),
                    ]),
                
                Section::make('Recipient Address')
                    ->schema([
                        TextInput::make('recipient_name')->required(),
                        TextInput::make('recipient_phone')->required(),
                        TextInput::make('recipient_address_line1')->required(),
                        TextInput::make('recipient_address_line2'),
                        Grid::make(3)->schema([
                            TextInput::make('recipient_city'),
                            TextInput::make('recipient_state')->required(),
                            TextInput::make('recipient_postcode')->required(),
                        ]),
                    ]),
                
                Section::make('Package Details')
                    ->schema([
                        TextInput::make('total_weight_grams')
                            ->numeric()
                            ->suffix('g')
                            ->required(),
                        TextInput::make('package_count')
                            ->numeric()
                            ->default(1),
                        Grid::make(3)->schema([
                            TextInput::make('length_cm')->numeric(),
                            TextInput::make('width_cm')->numeric(),
                            TextInput::make('height_cm')->numeric(),
                        ]),
                    ])
                    ->columns(2),
                
                Section::make('Options')
                    ->schema([
                        TextInput::make('cod_amount_minor')
                            ->label('COD Amount')
                            ->numeric()
                            ->prefix('RM'),
                        TextInput::make('insurance_value_minor')
                            ->label('Insurance Value')
                            ->numeric()
                            ->prefix('RM'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tracking_number')
                    ->searchable()
                    ->copyable(),
                
                TextColumn::make('carrier_id')
                    ->label('Carrier')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Carrier::find($state)?->name ?? $state),
                
                TextColumn::make('recipient_address')
                    ->label('Recipient')
                    ->formatStateUsing(fn ($state) => 
                        ($state['name'] ?? '') . ' - ' . ($state['state'] ?? '')),
                
                TextColumn::make('tracking_status')
                    ->badge()
                    ->color(fn ($state) => TrackingStatus::tryFrom($state)?->getStage()->color()),
                
                TextColumn::make('estimated_delivery_date')
                    ->date()
                    ->label('Est. Delivery'),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('carrier_id')
                    ->label('Carrier')
                    ->options(Carrier::pluck('name', 'id')),
                
                SelectFilter::make('tracking_status')
                    ->options(TrackingStatus::class),
                
                Filter::make('has_issues')
                    ->query(fn ($query) => $query->whereIn('tracking_status', [
                        'exception', 'delayed', 'address_issue',
                    ])),
                
                Filter::make('created_today')
                    ->query(fn ($query) => $query->whereDate('created_at', today())),
            ])
            ->actions([
                Action::make('track')
                    ->icon('heroicon-o-map-pin')
                    ->url(fn ($record) => $record->getTrackingUrl())
                    ->openUrlInNewTab(),
                
                Action::make('print_label')
                    ->icon('heroicon-o-printer')
                    ->action(fn ($record) => redirect($record->label_url))
                    ->visible(fn ($record) => $record->label_url),
            ])
            ->bulkActions([
                BulkAction::make('print_labels')
                    ->icon('heroicon-o-printer')
                    ->action(fn ($records) => $this->bulkPrintLabels($records)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TrackingEventsRelationManager::class,
        ];
    }
}
```

---

## Rate Comparison Page

### RateComparisonPage

```php
class RateComparisonPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Shipping';
    protected static ?string $title = 'Rate Calculator';
    
    public ?string $originPostcode = null;
    public ?string $destinationPostcode = null;
    public ?int $weightGrams = null;
    public ?int $length = null;
    public ?int $width = null;
    public ?int $height = null;
    public ?int $codAmount = null;
    public bool $includeInsurance = false;
    
    public ?RateComparisonResult $result = null;

    protected function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('originPostcode')
                    ->label('Origin Postcode')
                    ->required(),
                TextInput::make('destinationPostcode')
                    ->label('Destination Postcode')
                    ->required(),
            ]),
            
            Grid::make(4)->schema([
                TextInput::make('weightGrams')
                    ->label('Weight (g)')
                    ->numeric()
                    ->required(),
                TextInput::make('length')
                    ->label('Length (cm)')
                    ->numeric(),
                TextInput::make('width')
                    ->label('Width (cm)')
                    ->numeric(),
                TextInput::make('height')
                    ->label('Height (cm)')
                    ->numeric(),
            ]),
            
            Grid::make(2)->schema([
                TextInput::make('codAmount')
                    ->label('COD Amount')
                    ->numeric()
                    ->prefix('RM'),
                Toggle::make('includeInsurance')
                    ->label('Include Insurance'),
            ]),
        ];
    }

    public function calculate(): void
    {
        $request = new RateRequest(
            origin: new AddressData(postcode: $this->originPostcode),
            destination: new AddressData(postcode: $this->destinationPostcode),
            package: new PackageData(
                weightGrams: $this->weightGrams,
                lengthCm: $this->length,
                widthCm: $this->width,
                heightCm: $this->height,
            ),
            codAmount: $this->codAmount ? new MoneyData($this->codAmount * 100) : null,
            includeInsurance: $this->includeInsurance,
        );

        $this->result = app(RateCalculatorService::class)->getRates($request);
    }
}
```

---

## Return Management Resource

### ReturnRequestResource

```php
class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationGroup = 'Shipping';
    protected static ?string $navigationLabel = 'Returns';
    
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()
            ->whereIn('status', ['requested', 'approved', 'received'])
            ->count();
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rma_number')
                    ->searchable()
                    ->copyable(),
                
                TextColumn::make('order.order_number')
                    ->label('Order'),
                
                TextColumn::make('reason')
                    ->badge(),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ReturnStatus $state) => match ($state) {
                        ReturnStatus::Resolved => 'success',
                        ReturnStatus::Rejected, ReturnStatus::Cancelled => 'danger',
                        ReturnStatus::Inspecting, ReturnStatus::Resolving => 'warning',
                        default => 'gray',
                    }),
                
                TextColumn::make('resolution_type')
                    ->badge()
                    ->visible(fn ($record) => $record->resolution_type),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ReturnStatus::class),
                
                SelectFilter::make('reason')
                    ->options(ReturnReason::class),
            ])
            ->actions([
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn ($record) => app(ReturnService::class)->approve($record))
                    ->visible(fn ($record) => $record->status === ReturnStatus::Requested),
                
                Action::make('generate_label')
                    ->icon('heroicon-o-printer')
                    ->action(fn ($record) => app(ReturnService::class)->generateReturnLabel($record))
                    ->visible(fn ($record) => $record->status === ReturnStatus::Approved),
                
                Action::make('mark_received')
                    ->icon('heroicon-o-inbox')
                    ->action(fn ($record) => app(ReturnService::class)->markReceived($record))
                    ->visible(fn ($record) => $record->status === ReturnStatus::LabelGenerated),
            ]);
    }
}
```

---

## Carrier Management Resource

### CarrierResource

```php
class CarrierResource extends Resource
{
    protected static ?string $model = Carrier::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Carriers';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Carrier Details')
                    ->schema([
                        TextInput::make('id')
                            ->label('Identifier')
                            ->required()
                            ->disabled(fn ($record) => $record?->exists),
                        
                        TextInput::make('name')
                            ->required(),
                        
                        TextInput::make('driver')
                            ->required(),
                        
                        Toggle::make('is_active')
                            ->default(true),
                        
                        TextInput::make('priority')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),
                
                Section::make('Credentials')
                    ->relationship('credentials')
                    ->schema([
                        Toggle::make('is_sandbox')
                            ->label('Sandbox Mode'),
                        
                        KeyValue::make('credentials')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                    ]),
                
                Section::make('Capabilities')
                    ->schema([
                        Toggle::make('capabilities.supports_rate_quotes'),
                        Toggle::make('capabilities.supports_address_validation'),
                        Toggle::make('capabilities.supports_returns'),
                        Toggle::make('capabilities.supports_cod'),
                        Toggle::make('capabilities.supports_insurance'),
                    ])
                    ->columns(3),
            ]);
    }
}
```

---

## Navigation Structure

```
Shipping
├── Dashboard
├── Shipments
│   ├── All Shipments
│   ├── Pending
│   ├── In Transit
│   └── Exceptions
├── Rate Calculator
├── Returns
│   ├── All Returns
│   └── Pending Approval

Analytics
├── Carrier Performance
├── Delivery Times
├── Cost Analysis
└── Exception Reports

Settings
├── Carriers
├── Rate Cards
├── Shipping Rules
├── Zones
└── Notifications
```

---

## Navigation

**Previous:** [07-database-evolution.md](07-database-evolution.md)  
**Next:** [09-implementation-roadmap.md](09-implementation-roadmap.md)

# Filament Admin Enhancements

> **Document:** 10 of 11  
> **Package:** `aiarmada/filament-affiliates`  
> **Status:** 🟡 35% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Enhance the **Filament admin panel** for affiliate management with advanced dashboard widgets, enhanced resources, bulk operations, approval workflows, and real-time analytics.

---

## Current State ✅

### Implemented Features

1. **AffiliateResource**
   - Full CRUD operations
   - Table with status badges, filters, search
   - Form with validation
   - Relationship display (conversions, payouts)
   - Soft delete support

2. **AffiliateConversionResource**
   - Conversion listing with affiliate relationship
   - Status filtering and search
   - Commission amount display
   - Date filtering

3. **AffiliatePayoutResource**
   - Payout management CRUD
   - Status badges (pending, processing, completed, failed)
   - Amount formatting
   - Affiliate relationship display

4. **AffiliateStatsWidget**
   - Total affiliates count
   - Active affiliates
   - Pending commissions
   - Monthly revenue summary
   - Configurable card layout

5. **Plugin System**
   - `AffiliatesPlugin` class
   - Resource registration
   - Widget registration
   - Navigation group configuration

6. **Services**
   - `AffiliateReportingService` - Filament-specific reports
   - `PayoutExportService` - CSV export functionality
   - `AffiliateResourceBridge` - Core-Filament integration
   - `PayoutResourceBridge` - Payout integration

### Limitations (To Be Addressed)

- No approval workflow actions
- No bulk approve/reject operations
- No advanced charts/graphs
- No real-time dashboard updates
- No fraud alert widgets
- No payout scheduler interface
- No commission preview tools
- No affiliate performance comparisons
- No export to multiple formats (PDF, Excel)

---

## Enhanced Dashboard Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                 AFFILIATE DASHBOARD                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────────────────────────────────────────────────┐  │
│  │                    STAT WIDGETS                        │  │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐         │  │
│  │  │ Total  │ │ Active │ │Pending │ │ Today  │         │  │
│  │  │Affils  │ │Affils  │ │Payouts │ │Revenue │         │  │
│  │  └────────┘ └────────┘ └────────┘ └────────┘         │  │
│  └───────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌─────────────────────┐  ┌─────────────────────────────┐  │
│  │   REVENUE CHART     │  │    CONVERSIONS CHART        │  │
│  │   (Line/Area)       │  │    (Bar/Trend)              │  │
│  │                     │  │                             │  │
│  └─────────────────────┘  └─────────────────────────────┘  │
│                                                              │
│  ┌─────────────────────┐  ┌─────────────────────────────┐  │
│  │  PENDING APPROVALS  │  │   TOP PERFORMERS            │  │
│  │  (Action List)      │  │   (Leaderboard)             │  │
│  └─────────────────────┘  └─────────────────────────────┘  │
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │                   FRAUD ALERTS                           ││
│  │  (Priority notifications requiring attention)            ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Enhanced Widgets

### RevenueChartWidget

```php
class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Affiliate Revenue';
    
    protected static ?int $sort = 2;
    
    protected int|string|array $columnSpan = 'full';
    
    protected function getData(): array
    {
        $data = AffiliateConversion::query()
            ->where('status', ConversionStatus::Completed)
            ->whereBetween('occurred_at', [
                now()->subDays(30),
                now(),
            ])
            ->selectRaw('DATE(occurred_at) as date')
            ->selectRaw('SUM(order_amount_minor) / 100 as revenue')
            ->selectRaw('SUM(commission_amount_minor) / 100 as commissions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('revenue')->toArray(),
                    'borderColor' => '#10B981',
                    'fill' => true,
                ],
                [
                    'label' => 'Commissions',
                    'data' => $data->pluck('commissions')->toArray(),
                    'borderColor' => '#F59E0B',
                    'fill' => false,
                ],
            ],
            'labels' => $data->pluck('date')->map(fn ($d) => Carbon::parse($d)->format('M d'))->toArray(),
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}
```

### TopPerformersWidget

```php
class TopPerformersWidget extends Widget
{
    protected static ?int $sort = 4;
    
    protected int|string|array $columnSpan = 1;
    
    public function getViewData(): array
    {
        $topAffiliates = Affiliate::query()
            ->withSum(['conversions' => fn ($q) => $q
                ->where('status', ConversionStatus::Completed)
                ->where('occurred_at', '>=', now()->startOfMonth())
            ], 'order_amount_minor')
            ->orderByDesc('conversions_sum_order_amount_minor')
            ->limit(10)
            ->get();
        
        return [
            'affiliates' => $topAffiliates,
        ];
    }
}
```

### PendingApprovalsWidget

```php
class PendingApprovalsWidget extends Widget
{
    protected static ?int $sort = 3;
    
    public function getViewData(): array
    {
        return [
            'pendingAffiliates' => Affiliate::query()
                ->where('status', AffiliateStatus::Pending)
                ->latest()
                ->limit(5)
                ->get(),
            'pendingPayouts' => AffiliatePayout::query()
                ->where('status', 'pending')
                ->with('affiliate')
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }
    
    public function approve(string $affiliateId): void
    {
        $affiliate = Affiliate::findOrFail($affiliateId);
        $affiliate->update([
            'status' => AffiliateStatus::Active,
            'approved_at' => now(),
        ]);
        
        Notification::make()
            ->title('Affiliate approved')
            ->success()
            ->send();
    }
}
```

### FraudAlertsWidget

```php
class FraudAlertsWidget extends Widget
{
    protected static ?int $sort = 5;
    
    protected int|string|array $columnSpan = 'full';
    
    public function getViewData(): array
    {
        return [
            'alerts' => AffiliateFraudSignal::query()
                ->where('status', 'pending')
                ->with('affiliate')
                ->orderByDesc('severity')
                ->limit(10)
                ->get(),
        ];
    }
}
```

---

## Enhanced Resources

### Bulk Actions

```php
class AffiliateResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // existing columns...
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn ($affiliate) => 
                                $affiliate->update([
                                    'status' => AffiliateStatus::Active,
                                    'approved_at' => now(),
                                ])
                            );
                        }),
                    
                    Tables\Actions\BulkAction::make('suspend')
                        ->label('Suspend Selected')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Suspension Reason')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn ($affiliate) => 
                                $affiliate->update([
                                    'status' => AffiliateStatus::Suspended,
                                    'suspension_reason' => $data['reason'],
                                ])
                            );
                        }),
                    
                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            return response()->streamDownload(
                                fn () => print(
                                    app(AffiliateExportService::class)
                                        ->exportToCsv($records)
                                ),
                                'affiliates-export.csv'
                            );
                        }),
                ]),
            ]);
    }
}
```

### Approval Workflow

```php
class AffiliateResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => $record->status === AffiliateStatus::Pending)
                        ->requiresConfirmation()
                        ->action(function ($record): void {
                            $record->update([
                                'status' => AffiliateStatus::Active,
                                'approved_at' => now(),
                            ]);
                            
                            event(new AffiliateApproved($record));
                        }),
                    
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => $record->status === AffiliateStatus::Pending)
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Rejection Reason')
                                ->required(),
                        ])
                        ->action(function ($record, array $data): void {
                            $record->update([
                                'status' => AffiliateStatus::Rejected,
                                'rejection_reason' => $data['reason'],
                            ]);
                            
                            event(new AffiliateRejected($record, $data['reason']));
                        }),
                    
                    Tables\Actions\Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === AffiliateStatus::Active)
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Suspension Reason')
                                ->required(),
                        ])
                        ->action(function ($record, array $data): void {
                            $record->update([
                                'status' => AffiliateStatus::Suspended,
                                'suspension_reason' => $data['reason'],
                            ]);
                        }),
                ]),
            ]);
    }
}
```

---

## Real-Time Features

### Live Dashboard Updates

```php
class AffiliateStatsWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected function getStats(): array
    {
        return [
            Stat::make('Total Affiliates', Affiliate::count())
                ->description('All registered')
                ->icon('heroicon-o-users')
                ->chart($this->getAffiliateGrowthChart())
                ->color('primary'),
            
            Stat::make('Today\'s Revenue', $this->getTodaysRevenue())
                ->description($this->getRevenueChange())
                ->descriptionIcon($this->getRevenueIcon())
                ->icon('heroicon-o-currency-dollar')
                ->color($this->getRevenueColor()),
            
            Stat::make('Pending Payouts', $this->getPendingPayoutCount())
                ->description($this->getPendingPayoutAmount())
                ->icon('heroicon-o-clock')
                ->color('warning'),
            
            Stat::make('Conversion Rate', $this->getConversionRate())
                ->description('Last 30 days')
                ->icon('heroicon-o-chart-bar')
                ->chart($this->getConversionRateChart())
                ->color('success'),
        ];
    }
}
```

---

## Export Enhancements

### Multi-Format Export

```php
class AffiliateExportService
{
    public function export(Collection $affiliates, string $format): StreamedResponse
    {
        return match ($format) {
            'csv' => $this->exportToCsv($affiliates),
            'xlsx' => $this->exportToExcel($affiliates),
            'pdf' => $this->exportToPdf($affiliates),
            default => throw new InvalidArgumentException("Unsupported format: {$format}"),
        };
    }
    
    public function exportToExcel(Collection $affiliates): StreamedResponse
    {
        return Excel::download(
            new AffiliatesExport($affiliates),
            'affiliates-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
    
    public function exportToPdf(Collection $affiliates): StreamedResponse
    {
        $pdf = Pdf::loadView('affiliates::exports.affiliates', [
            'affiliates' => $affiliates,
            'generatedAt' => now(),
        ]);
        
        return response()->streamDownload(
            fn () => print($pdf->output()),
            'affiliates-' . now()->format('Y-m-d') . '.pdf'
        );
    }
}
```

---

## Implementation Priority

| Phase | Deliverable | Effort | Status |
|-------|-------------|--------|--------|
| 1 | AffiliateResource | 2 days | ✅ Done |
| 2 | AffiliateConversionResource | 1 day | ✅ Done |
| 3 | AffiliatePayoutResource | 1 day | ✅ Done |
| 4 | AffiliateStatsWidget | 1 day | ✅ Done |
| 5 | Plugin system | 0.5 day | ✅ Done |
| 6 | PayoutExportService | 0.5 day | ✅ Done |
| 7 | Resource bridges | 1 day | ✅ Done |
| 8 | Bulk approve/reject actions | 1 day | ⬜ Todo |
| 9 | Approval workflow actions | 1 day | ⬜ Todo |
| 10 | RevenueChartWidget | 1 day | ⬜ Todo |
| 11 | TopPerformersWidget | 0.5 day | ⬜ Todo |
| 12 | PendingApprovalsWidget | 0.5 day | ⬜ Todo |
| 13 | FraudAlertsWidget | 1 day | ⬜ Todo |
| 14 | Real-time polling | 0.5 day | ⬜ Todo |
| 15 | Multi-format export | 1 day | ⬜ Todo |
| 16 | Commission preview tool | 1 day | ⬜ Todo |

**Remaining Effort:** ~1.5 weeks

---

## Navigation

**Previous:** [09-database-evolution.md](09-database-evolution.md)  
**Next:** [11-implementation-roadmap.md](11-implementation-roadmap.md)

# Analytics & Reporting System

> **Document:** 05 of 11  
> **Package:** `aiarmada/affiliates`  
> **Status:** 🟡 45% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Build a comprehensive **analytics and reporting system** that provides real-time metrics, historical trend analysis, cohort analysis, and exportable reports for both administrators and affiliates.

---

## Current State ✅

### Implemented Features

1. **AffiliateStatsAggregator Service**
   - `getTotalAffiliates()` - Total count by status
   - `getPendingConversions()` - Count of pending conversions
   - `getPaidCommissions()` - Sum of paid commissions
   - `getTotalRevenue()` - Total conversion revenue
   - `getActiveAffiliates()` - Active affiliate count
   - Date range filtering support

2. **AffiliateReportService**
   - `generatePerformanceReport()` - Affiliate performance metrics
   - `generateConversionReport()` - Conversion details
   - `generatePayoutReport()` - Payout history
   - CSV export functionality
   - Date range filtering

3. **AffiliateStatsWidget (Filament)**
   - 5-stat dashboard widget
   - Displays: Total Affiliates, Active, Pending Conversions, Paid Commissions, Revenue
   - Configurable polling interval

4. **PayoutExportService**
   - CSV export of payout records
   - Filterable by affiliate, status, date range

5. **Basic Metrics**
   - Conversion count per affiliate
   - Commission totals per affiliate
   - Payout history per affiliate

### Limitations (To Be Addressed)

- No daily/monthly aggregation tables
- No cohort analysis
- No EPC (Earnings Per Click) calculation
- No conversion rate tracking
- No real-time dashboard updates (requires page refresh)
- No custom report builder
- Limited export formats (CSV only)
- No scheduled report delivery
- No multi-touch attribution analysis

---

## Vision Architecture

### Analytics Pipeline

```
┌─────────────────────────────────────────────────────────────┐
│                   ANALYTICS PIPELINE                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Raw Events ──► Aggregation ──► Metrics ──► Visualization   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              DATA COLLECTION                         │    │
│  │                                                      │    │
│  │  Clicks    Attributions    Conversions    Payouts   │    │
│  │     │           │              │             │       │    │
│  │     └───────────┴──────────────┴─────────────┘       │    │
│  │                      │                               │    │
│  │                      ▼                               │    │
│  │            ┌─────────────────────┐                  │    │
│  │            │   Daily Aggregates   │                  │    │
│  │            │   Hourly Aggregates  │                  │    │
│  │            │   Monthly Summaries  │                  │    │
│  │            └─────────────────────┘                  │    │
│  │                      │                               │    │
│  │                      ▼                               │    │
│  │            ┌─────────────────────┐                  │    │
│  │            │   Metric Calculator  │                  │    │
│  │            │   • EPC              │                  │    │
│  │            │   • Conversion Rate  │                  │    │
│  │            │   • AOV              │                  │    │
│  │            │   • LTV              │                  │    │
│  │            └─────────────────────┘                  │    │
│  │                      │                               │    │
│  │                      ▼                               │    │
│  │   Dashboard ◄── Reports ◄── Exports ◄── API        │    │
│  │                                                      │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Proposed Models

### AffiliateDailyStat

```php
/**
 * @property string $id
 * @property string $affiliate_id
 * @property Carbon $date
 * @property int $clicks
 * @property int $unique_clicks
 * @property int $attributions
 * @property int $conversions
 * @property int $revenue_cents
 * @property int $commission_cents
 * @property int $refunds
 * @property int $refund_amount_cents
 * @property float $conversion_rate
 * @property float $epc_cents
 * @property array $breakdown (by source, campaign, etc.)
 */
class AffiliateDailyStat extends Model
{
    use HasUuids;
    
    protected $casts = [
        'date' => 'date',
        'conversion_rate' => 'float',
        'epc_cents' => 'float',
        'breakdown' => 'array',
    ];
    
    public function affiliate(): BelongsTo;
    
    public function scopeForPeriod(Builder $query, Carbon $start, Carbon $end): Builder;
    public function scopeForAffiliate(Builder $query, string $affiliateId): Builder;
}
```

### AffiliateReport (Saved Reports)

```php
/**
 * @property string $id
 * @property string $name
 * @property string $type (performance, conversion, payout, network)
 * @property array $filters
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status (pending, processing, completed, failed)
 * @property string $file_path
 * @property array $summary
 */
class AffiliateReport extends Model
{
    use HasUuids;
    
    public function scopeForOwner(Builder $query, Model $owner): Builder;
}
```

---

## Enhanced Services

### AffiliateAnalyticsService

```php
class AffiliateAnalyticsService
{
    public function __construct(
        private AffiliateStatsAggregator $aggregator,
        private CacheManager $cache,
    ) {}
    
    public function getDashboardMetrics(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(30);
        $to ??= now();
        
        return $this->cache->remember(
            "affiliate_dashboard_{$from->format('Ymd')}_{$to->format('Ymd')}",
            3600,
            fn () => [
                'overview' => $this->getOverviewMetrics($from, $to),
                'trends' => $this->getTrendData($from, $to),
                'top_performers' => $this->getTopPerformers($from, $to),
                'conversion_funnel' => $this->getConversionFunnel($from, $to),
            ]
        );
    }
    
    public function getAffiliateMetrics(
        Affiliate $affiliate,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {
        return [
            'performance' => $this->getPerformanceMetrics($affiliate, $from, $to),
            'earnings' => $this->getEarningsMetrics($affiliate, $from, $to),
            'network' => $this->getNetworkMetrics($affiliate),
            'comparison' => $this->getComparisonMetrics($affiliate, $from, $to),
        ];
    }
    
    public function calculateEpc(Affiliate $affiliate, Carbon $from, Carbon $to): float
    {
        $clicks = $affiliate->attributions()
            ->whereBetween('attributed_at', [$from, $to])
            ->count();
            
        $earnings = $affiliate->conversions()
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('commission_minor');
            
        return $clicks > 0 ? $earnings / $clicks : 0;
    }
    
    public function calculateConversionRate(
        Affiliate $affiliate,
        Carbon $from,
        Carbon $to
    ): float {
        $clicks = $affiliate->attributions()
            ->whereBetween('attributed_at', [$from, $to])
            ->count();
            
        $conversions = $affiliate->conversions()
            ->whereBetween('occurred_at', [$from, $to])
            ->count();
            
        return $clicks > 0 ? ($conversions / $clicks) * 100 : 0;
    }
}
```

### DailyAggregationService

```php
class DailyAggregationService
{
    public function aggregate(Carbon $date): int
    {
        $affiliateCount = 0;
        
        Affiliate::query()
            ->chunkById(100, function ($affiliates) use ($date, &$affiliateCount) {
                foreach ($affiliates as $affiliate) {
                    $this->aggregateForAffiliate($affiliate, $date);
                    $affiliateCount++;
                }
            });
        
        return $affiliateCount;
    }
    
    private function aggregateForAffiliate(Affiliate $affiliate, Carbon $date): void
    {
        $clicks = $affiliate->touchpoints()
            ->whereDate('visited_at', $date)
            ->count();
            
        $uniqueClicks = $affiliate->touchpoints()
            ->whereDate('visited_at', $date)
            ->distinct('ip_address')
            ->count();
            
        $attributions = $affiliate->attributions()
            ->whereDate('attributed_at', $date)
            ->count();
            
        $conversions = $affiliate->conversions()
            ->whereDate('occurred_at', $date);
            
        $conversionCount = $conversions->count();
        $revenue = $conversions->sum('total_minor');
        $commission = $conversions->sum('commission_minor');
        
        AffiliateDailyStat::updateOrCreate(
            [
                'affiliate_id' => $affiliate->id,
                'date' => $date,
            ],
            [
                'clicks' => $clicks,
                'unique_clicks' => $uniqueClicks,
                'attributions' => $attributions,
                'conversions' => $conversionCount,
                'revenue_cents' => $revenue,
                'commission_cents' => $commission,
                'conversion_rate' => $clicks > 0 ? $conversionCount / $clicks : 0,
                'epc_cents' => $clicks > 0 ? $commission / $clicks : 0,
            ]
        );
    }
}
```

---

## Report Types

### Performance Report

```php
class PerformanceReportGenerator
{
    public function generate(array $filters): AffiliateReport
    {
        $query = Affiliate::query()
            ->withCount(['conversions', 'attributions'])
            ->withSum('conversions', 'total_minor')
            ->withSum('conversions', 'commission_minor');
        
        // Apply filters
        if ($filters['program_id'] ?? null) {
            $query->where('program_id', $filters['program_id']);
        }
        
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        
        $data = $query->get()->map(fn ($affiliate) => [
            'affiliate_id' => $affiliate->id,
            'name' => $affiliate->name,
            'program' => $affiliate->program?->name,
            'tier' => $affiliate->tier?->name,
            'clicks' => $affiliate->attributions_count,
            'conversions' => $affiliate->conversions_count,
            'revenue' => $affiliate->conversions_sum_total_minor,
            'commission' => $affiliate->conversions_sum_commission_minor,
            'conversion_rate' => $affiliate->attributions_count > 0 
                ? ($affiliate->conversions_count / $affiliate->attributions_count) * 100 
                : 0,
            'epc' => $affiliate->attributions_count > 0
                ? $affiliate->conversions_sum_commission_minor / $affiliate->attributions_count
                : 0,
        ]);
        
        return $this->createReport('performance', $filters, $data);
    }
}
```

### Cohort Analysis Report

```php
class CohortAnalysisGenerator
{
    public function generate(string $cohortType = 'monthly'): array
    {
        $cohorts = [];
        
        $affiliates = Affiliate::query()
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($a) => match ($cohortType) {
                'weekly' => $a->created_at->startOfWeek()->format('Y-W'),
                'monthly' => $a->created_at->format('Y-m'),
                'quarterly' => $a->created_at->quarter . 'Q' . $a->created_at->year,
            });
        
        foreach ($affiliates as $cohortKey => $cohortAffiliates) {
            $cohorts[$cohortKey] = [
                'size' => $cohortAffiliates->count(),
                'retention' => $this->calculateRetention($cohortAffiliates),
                'revenue' => $this->calculateCohortRevenue($cohortAffiliates),
                'avg_conversions' => $this->calculateAvgConversions($cohortAffiliates),
            ];
        }
        
        return $cohorts;
    }
}
```

---

## Database Schema

```php
// affiliate_daily_stats table
Schema::create('affiliate_daily_stats', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->date('date');
    $table->integer('clicks')->default(0);
    $table->integer('unique_clicks')->default(0);
    $table->integer('attributions')->default(0);
    $table->integer('conversions')->default(0);
    $table->bigInteger('revenue_cents')->default(0);
    $table->bigInteger('commission_cents')->default(0);
    $table->integer('refunds')->default(0);
    $table->bigInteger('refund_amount_cents')->default(0);
    $table->decimal('conversion_rate', 8, 4)->default(0);
    $table->decimal('epc_cents', 10, 4)->default(0);
    $table->json('breakdown')->nullable();
    $table->timestamps();
    
    $table->unique(['affiliate_id', 'date']);
    $table->index(['date', 'revenue_cents']);
});

// affiliate_reports table
Schema::create('affiliate_reports', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('type');
    $table->json('filters');
    $table->date('start_date');
    $table->date('end_date');
    $table->string('status');
    $table->string('file_path')->nullable();
    $table->json('summary')->nullable();
    $table->nullableUuidMorphs('owner');
    $table->timestamps();
    
    $table->index('status');
});
```

---

## Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `DailyStatsAggregated` | Daily aggregation complete | date, affiliateCount |
| `ReportGenerated` | Report generation complete | report |
| `ReportFailed` | Report generation failed | report, error |

---

## Implementation Priority

| Phase | Deliverable | Effort | Status |
|-------|-------------|--------|--------|
| 1 | AffiliateStatsAggregator | 2 days | ✅ Done |
| 2 | AffiliateReportService | 2 days | ✅ Done |
| 3 | AffiliateStatsWidget | 1 day | ✅ Done |
| 4 | PayoutExportService | 1 day | ✅ Done |
| 5 | AffiliateDailyStat model | 2 days | ⬜ Todo |
| 6 | DailyAggregationService | 2 days | ⬜ Todo |
| 7 | EPC & conversion rate metrics | 1 day | ⬜ Todo |
| 8 | Cohort analysis | 2 days | ⬜ Todo |
| 9 | Custom report builder | 3 days | ⬜ Todo |
| 10 | Scheduled reports | 2 days | ⬜ Todo |
| 11 | PDF/Excel export | 2 days | ⬜ Todo |

**Remaining Effort:** ~2 weeks

---

## Navigation

**Previous:** [04-fraud-detection.md](04-fraud-detection.md)  
**Next:** [06-affiliate-portal.md](06-affiliate-portal.md)

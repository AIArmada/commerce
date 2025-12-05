# Analytics & Insights

> **Document:** 05 of 10  
> **Package:** `aiarmada/chip`  
> **Status:** Vision

---

## Overview

Build a comprehensive **analytics and insights system** that provides revenue metrics, payment method analysis, failure tracking, and actionable business intelligence.

---

## Analytics Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                   ANALYTICS PIPELINE                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Raw Events (Webhooks)                                       │
│        │                                                     │
│        ▼                                                     │
│  ┌─────────────────┐                                        │
│  │   Aggregator    │ ─── Hourly/Daily rollups               │
│  └────────┬────────┘                                        │
│           │                                                  │
│           ▼                                                  │
│  ┌─────────────────┐                                        │
│  │  Metrics Store  │ ─── chip_daily_metrics                 │
│  └────────┬────────┘                                        │
│           │                                                  │
│           ▼                                                  │
│  ┌─────────────────┐                                        │
│  │   Dashboard     │ ─── Widgets, charts, reports           │
│  └─────────────────┘                                        │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Core Metrics

### Revenue Metrics

| Metric | Formula | Description |
|--------|---------|-------------|
| GMV | Sum of all completed payments | Gross Merchandise Value |
| Net Revenue | GMV - Refunds - Fees | Actual revenue |
| ARPU | Revenue / Unique Customers | Average Revenue Per User |
| Transaction Volume | Count of payments | Number of transactions |
| AOV | GMV / Transaction Count | Average Order Value |
| Refund Rate | Refunds / GMV × 100 | Percentage refunded |

### Performance Metrics

| Metric | Formula | Description |
|--------|---------|-------------|
| Success Rate | Successful / Attempted × 100 | Payment success percentage |
| Failure Rate | Failed / Attempted × 100 | Payment failure percentage |
| Retry Success | Retried Success / Total Retries | Retry effectiveness |
| Avg Processing Time | Sum(time) / Count | Time to complete |
| Authorization Rate | Authorized / Submitted | Pre-capture success |

---

## Analytics Service

### ChipAnalyticsService

```php
class ChipAnalyticsService
{
    public function __construct(
        private MetricsAggregator $aggregator,
        private RevenueCalculator $revenueCalculator,
        private FailureAnalyzer $failureAnalyzer,
    ) {}
    
    /**
     * Get dashboard overview metrics
     */
    public function getDashboardMetrics(Carbon $startDate, Carbon $endDate): DashboardMetrics
    {
        return new DashboardMetrics(
            revenue: $this->revenueCalculator->calculate($startDate, $endDate),
            transactions: $this->getTransactionMetrics($startDate, $endDate),
            paymentMethods: $this->getPaymentMethodBreakdown($startDate, $endDate),
            failures: $this->failureAnalyzer->analyze($startDate, $endDate),
            trends: $this->calculateTrends($startDate, $endDate),
        );
    }
    
    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics(): array
    {
        $now = now();
        $hourAgo = $now->copy()->subHour();
        
        return [
            'transactions_last_hour' => $this->getTransactionCount($hourAgo, $now),
            'revenue_last_hour' => $this->getRevenue($hourAgo, $now),
            'active_transactions' => $this->getActiveTransactionCount(),
            'current_success_rate' => $this->getCurrentSuccessRate(),
            'avg_response_time_ms' => $this->getAverageResponseTime(),
        ];
    }
    
    /**
     * Get revenue breakdown
     */
    public function getRevenueBreakdown(
        Carbon $startDate,
        Carbon $endDate,
        string $groupBy = 'day'
    ): array {
        $interval = match ($groupBy) {
            'hour' => 'DATE_FORMAT(completed_at, "%Y-%m-%d %H:00:00")',
            'day' => 'DATE(completed_at)',
            'week' => 'YEARWEEK(completed_at)',
            'month' => 'DATE_FORMAT(completed_at, "%Y-%m-01")',
            default => 'DATE(completed_at)',
        };
        
        return ChipPurchase::query()
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->selectRaw("{$interval} as period, 
                COUNT(*) as count,
                SUM(total_minor) as revenue,
                AVG(total_minor) as avg_order_value")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }
}
```

---

## Payment Method Analytics

### PaymentMethodAnalyzer

```php
class PaymentMethodAnalyzer
{
    public function getBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        return ChipPurchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                payment_method,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = "paid" THEN total_minor ELSE 0 END) as revenue,
                AVG(CASE WHEN status = "paid" THEN total_minor ELSE NULL END) as avg_value,
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_time_seconds
            ')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->payment_method,
                'method_name' => $this->formatMethodName($row->payment_method),
                'attempts' => $row->total_attempts,
                'successful' => $row->successful,
                'success_rate' => round($row->successful / $row->total_attempts * 100, 2),
                'revenue' => $row->revenue,
                'avg_value' => $row->avg_value,
                'avg_time_seconds' => round($row->avg_time_seconds, 2),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->toArray();
    }
    
    public function getMethodTrends(
        string $method,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        return ChipPurchase::query()
            ->where('payment_method', $method)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as attempts,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = "paid" THEN total_minor ELSE 0 END) as revenue
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }
}
```

---

## Failure Analysis

### FailureAnalyzer

```php
class FailureAnalyzer
{
    public function analyze(Carbon $startDate, Carbon $endDate): FailureAnalysis
    {
        $failures = ChipPurchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['failed', 'error'])
            ->get();
        
        return new FailureAnalysis(
            totalFailures: $failures->count(),
            totalAmount: $failures->sum('total_minor'),
            byReason: $this->groupByReason($failures),
            byMethod: $this->groupByMethod($failures),
            byHour: $this->groupByHour($failures),
            recoverable: $this->identifyRecoverable($failures),
        );
    }
    
    public function getFailureReasons(Carbon $startDate, Carbon $endDate): array
    {
        return ChipPurchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['failed', 'error'])
            ->selectRaw('
                failure_reason,
                COUNT(*) as count,
                SUM(total_minor) as lost_revenue
            ')
            ->groupBy('failure_reason')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'reason' => $row->failure_reason,
                'description' => $this->describeFailureReason($row->failure_reason),
                'count' => $row->count,
                'lost_revenue' => $row->lost_revenue,
                'is_recoverable' => $this->isRecoverable($row->failure_reason),
                'suggestion' => $this->getSuggestion($row->failure_reason),
            ])
            ->toArray();
    }
    
    private function describeFailureReason(string $reason): string
    {
        return match ($reason) {
            'insufficient_funds' => 'Customer has insufficient funds',
            'card_declined' => 'Card was declined by issuer',
            'expired_card' => 'Card has expired',
            'invalid_card' => 'Invalid card number',
            'authentication_failed' => '3DS authentication failed',
            'timeout' => 'Transaction timed out',
            'network_error' => 'Network connectivity issue',
            'duplicate_transaction' => 'Duplicate transaction detected',
            'limit_exceeded' => 'Transaction limit exceeded',
            default => 'Unknown failure reason',
        };
    }
    
    private function getSuggestion(string $reason): string
    {
        return match ($reason) {
            'insufficient_funds' => 'Implement retry with smaller amount or payment plan option',
            'authentication_failed' => 'Review 3DS implementation, consider frictionless flow',
            'timeout' => 'Check network latency, implement longer timeout',
            'expired_card' => 'Prompt customer to update card details',
            default => 'Review transaction details and contact support if persistent',
        };
    }
}
```

---

## Aggregated Metrics Model

### ChipDailyMetric

```php
/**
 * Pre-aggregated daily metrics
 * 
 * @property string $id
 * @property Carbon $date
 * @property string|null $payment_method
 * @property int $total_attempts
 * @property int $successful_count
 * @property int $failed_count
 * @property int $refunded_count
 * @property int $revenue_minor
 * @property int $refunds_minor
 * @property int $fees_minor
 * @property float $success_rate
 * @property float $avg_transaction_minor
 * @property float $avg_processing_seconds
 * @property array|null $failure_breakdown
 */
class ChipDailyMetric extends Model
{
    use HasUuids;
    
    protected $casts = [
        'date' => 'date',
        'success_rate' => 'float',
        'avg_transaction_minor' => 'float',
        'avg_processing_seconds' => 'float',
        'failure_breakdown' => 'array',
    ];
    
    public function getTable(): string
    {
        return config('chip.database.tables.daily_metrics')
            ?? config('chip.database.table_prefix', 'chip_') . 'daily_metrics';
    }
}
```

### MetricsAggregator

```php
class MetricsAggregator
{
    /**
     * Aggregate metrics for a specific date
     */
    public function aggregateForDate(Carbon $date): void
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();
        
        // Aggregate by payment method
        $byMethod = ChipPurchase::query()
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw('
                payment_method,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as successful_count,
                SUM(CASE WHEN status IN ("failed", "error") THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = "refunded" THEN 1 ELSE 0 END) as refunded_count,
                SUM(CASE WHEN status = "paid" THEN total_minor ELSE 0 END) as revenue_minor,
                SUM(CASE WHEN status = "refunded" THEN refund_amount_minor ELSE 0 END) as refunds_minor,
                AVG(CASE WHEN status = "paid" THEN total_minor ELSE NULL END) as avg_transaction,
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_processing
            ')
            ->groupBy('payment_method')
            ->get();
        
        foreach ($byMethod as $row) {
            ChipDailyMetric::updateOrCreate(
                [
                    'date' => $date,
                    'payment_method' => $row->payment_method,
                ],
                [
                    'total_attempts' => $row->total_attempts,
                    'successful_count' => $row->successful_count,
                    'failed_count' => $row->failed_count,
                    'refunded_count' => $row->refunded_count,
                    'revenue_minor' => $row->revenue_minor ?? 0,
                    'refunds_minor' => $row->refunds_minor ?? 0,
                    'success_rate' => $row->total_attempts > 0 
                        ? $row->successful_count / $row->total_attempts * 100 
                        : 0,
                    'avg_transaction_minor' => $row->avg_transaction ?? 0,
                    'avg_processing_seconds' => $row->avg_processing ?? 0,
                    'failure_breakdown' => $this->getFailureBreakdown($date, $row->payment_method),
                ]
            );
        }
        
        // Aggregate totals (null payment_method)
        $this->aggregateTotals($date);
    }
}
```

---

## Revenue Calculator

### RevenueCalculator

```php
class RevenueCalculator
{
    public function calculate(Carbon $startDate, Carbon $endDate): RevenueMetrics
    {
        $metrics = ChipDailyMetric::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNull('payment_method') // totals only
            ->selectRaw('
                SUM(revenue_minor) as total_revenue,
                SUM(refunds_minor) as total_refunds,
                SUM(fees_minor) as total_fees,
                SUM(successful_count) as total_transactions,
                AVG(avg_transaction_minor) as avg_transaction
            ')
            ->first();
        
        $previousPeriod = $this->getPreviousPeriodMetrics($startDate, $endDate);
        
        return new RevenueMetrics(
            grossRevenue: $metrics->total_revenue ?? 0,
            refunds: $metrics->total_refunds ?? 0,
            fees: $metrics->total_fees ?? 0,
            netRevenue: ($metrics->total_revenue ?? 0) - ($metrics->total_refunds ?? 0) - ($metrics->total_fees ?? 0),
            transactionCount: $metrics->total_transactions ?? 0,
            averageTransaction: $metrics->avg_transaction ?? 0,
            growthRate: $this->calculateGrowthRate($metrics, $previousPeriod),
        );
    }
    
    public function getMonthlyRecurringRevenue(): int
    {
        return ChipSubscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->join('chip_plans', 'chip_subscriptions.plan_id', '=', 'chip_plans.id')
            ->selectRaw('SUM(
                CASE chip_plans.interval
                    WHEN "daily" THEN chip_plans.price_minor * 30
                    WHEN "weekly" THEN chip_plans.price_minor * 4
                    WHEN "monthly" THEN chip_plans.price_minor
                    WHEN "yearly" THEN chip_plans.price_minor / 12
                END * chip_subscriptions.quantity
            ) as mrr')
            ->value('mrr') ?? 0;
    }
}
```

---

## Cohort Analysis

### CustomerCohortAnalyzer

```php
class CustomerCohortAnalyzer
{
    public function getRetentionCohorts(
        Carbon $startDate,
        Carbon $endDate,
        string $period = 'month'
    ): array {
        // Group customers by first purchase month
        $cohorts = ChipPurchase::query()
            ->where('status', 'paid')
            ->selectRaw('
                customer_email,
                MIN(DATE_FORMAT(completed_at, "%Y-%m-01")) as cohort_month
            ')
            ->groupBy('customer_email')
            ->havingRaw('cohort_month BETWEEN ? AND ?', [$startDate, $endDate])
            ->get()
            ->groupBy('cohort_month');
        
        $result = [];
        
        foreach ($cohorts as $cohortMonth => $customers) {
            $customerEmails = $customers->pluck('customer_email');
            $cohortData = [
                'cohort' => $cohortMonth,
                'size' => $customerEmails->count(),
                'retention' => [],
            ];
            
            // Calculate retention for each subsequent month
            for ($i = 0; $i <= 12; $i++) {
                $checkMonth = Carbon::parse($cohortMonth)->addMonths($i);
                
                $activeCount = ChipPurchase::query()
                    ->where('status', 'paid')
                    ->whereIn('customer_email', $customerEmails)
                    ->whereMonth('completed_at', $checkMonth->month)
                    ->whereYear('completed_at', $checkMonth->year)
                    ->distinct('customer_email')
                    ->count();
                
                $cohortData['retention'][$i] = [
                    'active' => $activeCount,
                    'rate' => round($activeCount / $customerEmails->count() * 100, 2),
                ];
            }
            
            $result[] = $cohortData;
        }
        
        return $result;
    }
}
```

---

## Database Schema

```php
// chip_daily_metrics table
Schema::create('chip_daily_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->date('date');
    $table->string('payment_method')->nullable(); // null = totals
    $table->integer('total_attempts')->default(0);
    $table->integer('successful_count')->default(0);
    $table->integer('failed_count')->default(0);
    $table->integer('refunded_count')->default(0);
    $table->bigInteger('revenue_minor')->default(0);
    $table->bigInteger('refunds_minor')->default(0);
    $table->bigInteger('fees_minor')->default(0);
    $table->decimal('success_rate', 5, 2)->default(0);
    $table->decimal('avg_transaction_minor', 12, 2)->default(0);
    $table->decimal('avg_processing_seconds', 8, 2)->default(0);
    $table->json('failure_breakdown')->nullable();
    $table->timestamps();
    
    $table->unique(['date', 'payment_method']);
    $table->index('date');
});
```

---

## Scheduled Aggregation

```php
// Aggregate yesterday's metrics
$schedule->command('chip:aggregate-metrics')
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Real-time aggregation (every 15 minutes)
$schedule->command('chip:aggregate-metrics --today')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
```

---

## Navigation

**Previous:** [04-dispute-management.md](04-dispute-management.md)  
**Next:** [06-enhanced-webhooks.md](06-enhanced-webhooks.md)

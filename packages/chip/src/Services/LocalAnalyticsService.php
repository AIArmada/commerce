<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use AIArmada\Chip\Models\Purchase;
use Carbon\CarbonImmutable;

/**
 * Local analytics service - computes metrics from local data.
 *
 * All SQL queries are database-agnostic and work with MySQL, PostgreSQL, and SQLite.
 */
class LocalAnalyticsService
{
    /**
     * Get comprehensive dashboard metrics from LOCAL data.
     */
    public function getDashboardMetrics(CarbonImmutable $startDate, CarbonImmutable $endDate): DashboardMetrics
    {
        return new DashboardMetrics(
            revenue: $this->getRevenueMetrics($startDate, $endDate),
            transactions: $this->getTransactionMetrics($startDate, $endDate),
            paymentMethods: $this->getPaymentMethodBreakdown($startDate, $endDate),
            failures: $this->getFailureAnalysis($startDate, $endDate),
        );
    }

    /**
     * Revenue metrics from local purchases.
     */
    public function getRevenueMetrics(CarbonImmutable $startDate, CarbonImmutable $endDate): RevenueMetrics
    {
        $metrics = Purchase::query()
            ->forOwner()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw("
                SUM(CASE WHEN status = 'paid' THEN total_minor ELSE 0 END) as revenue,
                SUM(CASE WHEN status = 'refunded' THEN refund_amount_minor ELSE 0 END) as refunds,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
                AVG(CASE WHEN status = 'paid' THEN total_minor END) as avg_transaction
            ")
            ->first();

        // Get previous period for comparison
        $periodLength = $startDate->diffInDays($endDate);
        $previousStart = $startDate->subDays($periodLength + 1);
        $previousEnd = $startDate->subDay();

        $previous = Purchase::query()
            ->forOwner()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('status', 'paid')
            ->sum('total_minor');

        $currentRevenue = $metrics->revenue ?? 0;
        $growthRate = $previous > 0
            ? (($currentRevenue - $previous) / $previous) * 100
            : ($currentRevenue > 0 ? 100 : 0);

        return new RevenueMetrics(
            grossRevenue: (int) $currentRevenue,
            refunds: (int) ($metrics->refunds ?? 0),
            netRevenue: (int) $currentRevenue - (int) ($metrics->refunds ?? 0),
            transactionCount: (int) ($metrics->paid_count ?? 0),
            averageTransaction: (float) ($metrics->avg_transaction ?? 0),
            growthRate: round($growthRate, 2),
        );
    }

    /**
     * Transaction metrics from local data.
     */
    public function getTransactionMetrics(CarbonImmutable $startDate, CarbonImmutable $endDate): TransactionMetrics
    {
        $metrics = Purchase::query()
            ->forOwner()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status IN ('failed', 'error') THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status IN ('pending', 'created') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded
            ")
            ->first();

        $total = $metrics->total ?? 0;
        $successful = $metrics->successful ?? 0;
        $successRate = $total > 0 ? round($successful / $total * 100, 2) : 0;

        return new TransactionMetrics(
            total: (int) $total,
            successful: (int) $successful,
            failed: (int) ($metrics->failed ?? 0),
            pending: (int) ($metrics->pending ?? 0),
            refunded: (int) ($metrics->refunded ?? 0),
            successRate: $successRate,
        );
    }

    /**
     * Payment method breakdown from local data.
     *
     * @return array<int, array{method: string, attempts: int, successful: int, success_rate: float, revenue: int}>
     */
    public function getPaymentMethodBreakdown(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        return Purchase::query()
            ->forOwner()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->toBase()
            ->selectRaw("
                COALESCE(payment_method, 'unknown') as payment_method,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'paid' THEN total_minor ELSE 0 END) as revenue
            ")
            ->groupBy('payment_method')
            ->get()
            ->map(fn (object $row): array => [
                'method' => (string) $row->payment_method,
                'attempts' => (int) $row->total_attempts,
                'successful' => (int) $row->successful,
                'success_rate' => (int) $row->total_attempts > 0
                    ? round(((int) $row->successful) / ((int) $row->total_attempts) * 100, 2)
                    : 0,
                'revenue' => (int) $row->revenue,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->toArray();
    }

    /**
     * Failure analysis from local data.
     *
     * @return array<int, array{reason: string, count: int, lost_revenue: int}>
     */
    public function getFailureAnalysis(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        return Purchase::query()
            ->forOwner()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['failed', 'error'])
            ->toBase()
            ->selectRaw("
                COALESCE(failure_reason, 'Unknown') as failure_reason,
                COUNT(*) as count,
                SUM(total_minor) as lost_revenue
            ")
            ->groupBy('failure_reason')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => [
                'reason' => (string) $row->failure_reason,
                'count' => (int) $row->count,
                'lost_revenue' => (int) ($row->lost_revenue ?? 0),
            ])
            ->toArray();
    }

    /**
     * Revenue trend for charts.
     *
     * Uses database-agnostic date truncation via PHP grouping.
     *
     * @return array<int, array{period: string, count: int, revenue: int}>
     */
    public function getRevenueTrend(CarbonImmutable $startDate, CarbonImmutable $endDate, string $groupBy = 'day'): array
    {
        // Fetch raw data and group in PHP for database portability
        $purchases = Purchase::query()
            ->forOwner()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->select(['created_at', 'total_minor'])
            ->get();

        return $purchases
            ->groupBy(fn ($purchase): string => $this->formatPeriod($purchase->created_at, $groupBy))
            ->map(fn ($group, string $period): array => [
                'period' => $period,
                'count' => $group->count(),
                'revenue' => (int) $group->sum('total_minor'),
            ])
            ->sortKeys()
            ->values()
            ->toArray();
    }

    /**
     * Format a date into a period string based on the grouping.
     */
    private function formatPeriod(mixed $date, string $groupBy): string
    {
        $carbon = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        return match ($groupBy) {
            'hour' => $carbon->format('Y-m-d H:00:00'),
            'day' => $carbon->format('Y-m-d'),
            'week' => $carbon->startOfWeek()->format('Y-m-d'),
            'month' => $carbon->format('Y-m-01'),
            default => $carbon->format('Y-m-d'),
        };
    }

}

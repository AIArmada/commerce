<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Api;

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use Carbon\CarbonImmutable;

interface PurchaseAnalytics
{
    public function getDashboardMetrics(CarbonImmutable $startDate, CarbonImmutable $endDate): DashboardMetrics;

    public function getRevenueMetrics(CarbonImmutable $startDate, CarbonImmutable $endDate): RevenueMetrics;

    public function getTransactionMetrics(CarbonImmutable $startDate, CarbonImmutable $endDate): TransactionMetrics;

    /** @return array<int, array{method: string, attempts: int, successful: int, success_rate: float, revenue: int}> */
    public function getPaymentMethodBreakdown(CarbonImmutable $startDate, CarbonImmutable $endDate): array;

    /** @return array<int, array{reason: string, count: int, lost_revenue: int}> */
    public function getFailureAnalysis(CarbonImmutable $startDate, CarbonImmutable $endDate): array;

    /** @return array<int, array{period: string, count: int, revenue: int}> */
    public function getRevenueTrend(CarbonImmutable $startDate, CarbonImmutable $endDate, string $groupBy = 'day'): array;
}

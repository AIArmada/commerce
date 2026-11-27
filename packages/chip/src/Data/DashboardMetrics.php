<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

/**
 * Aggregated dashboard metrics.
 */
final readonly class DashboardMetrics
{
    public function __construct(
        public RevenueMetrics $revenue,
        public TransactionMetrics $transactions,
        /** @var array<int, array{method: string, attempts: int, successful: int, success_rate: float, revenue: int}> */
        public array $paymentMethods,
        /** @var array<int, array{reason: string, count: int, lost_revenue: int}> */
        public array $failures,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'revenue' => $this->revenue->toArray(),
            'transactions' => $this->transactions->toArray(),
            'paymentMethods' => $this->paymentMethods,
            'failures' => $this->failures,
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\CommerceSupport\Support\MoneyFormatter;

/**
 * Revenue metrics from local data.
 */
final readonly class RevenueMetrics
{
    public function __construct(
        public int $grossRevenue,
        public int $refunds,
        public int $netRevenue,
        public int $transactionCount,
        public float $averageTransaction,
        public float $growthRate,
        public string $currency = 'MYR',
    ) {}

    /**
     * Get formatted gross revenue.
     */
    public function grossRevenueFormatted(): string
    {
        return MoneyFormatter::formatMinorWithCode($this->grossRevenue, $this->currency);
    }

    /**
     * Get formatted net revenue.
     */
    public function netRevenueFormatted(): string
    {
        return MoneyFormatter::formatMinorWithCode($this->netRevenue, $this->currency);
    }

    /**
     * Get formatted average transaction.
     */
    public function averageTransactionFormatted(): string
    {
        return MoneyFormatter::formatMinorWithCode((int) round($this->averageTransaction), $this->currency);
    }

    /**
     * Check if growth is positive.
     */
    public function hasPositiveGrowth(): bool
    {
        return $this->growthRate > 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'grossRevenue' => $this->grossRevenue,
            'refunds' => $this->refunds,
            'netRevenue' => $this->netRevenue,
            'transactionCount' => $this->transactionCount,
            'averageTransaction' => $this->averageTransaction,
            'growthRate' => $this->growthRate,
            'currency' => $this->currency,
        ];
    }
}

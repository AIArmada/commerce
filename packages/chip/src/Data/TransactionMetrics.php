<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

/**
 * Transaction metrics from local data.
 */
final readonly class TransactionMetrics
{
    public function __construct(
        public int $total,
        public int $successful,
        public int $failed,
        public int $pending,
        public int $refunded,
        public float $successRate,
    ) {}

    /**
     * Check if success rate is healthy (above 95%).
     */
    public function isHealthy(): bool
    {
        return $this->successRate >= 95;
    }

    /**
     * Get failure rate.
     */
    public function failureRate(): float
    {
        return $this->total > 0
            ? round($this->failed / $this->total * 100, 2)
            : 0.0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'successful' => $this->successful,
            'failed' => $this->failed,
            'pending' => $this->pending,
            'refunded' => $this->refunded,
            'successRate' => $this->successRate,
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\EntryPoints\Health;

use AIArmada\CommerceSupport\Health\CommerceHealthCheck;
use AIArmada\Inventory\Features\Stock\Api\StockHealth;
use InvalidArgumentException;
use Spatie\Health\Checks\Result;

/**
 * Health check for low stock inventory levels.
 */
class LowStockCheck extends CommerceHealthCheck
{
    public ?string $name = 'Low Stock Alert';

    /**
     * The threshold for low stock warning.
     */
    protected int $threshold = 10;

    /**
     * Whether to fail on low stock.
     */
    protected bool $failOnLowStock = false;

    /**
     * Set the low stock threshold.
     */
    public function threshold(int $threshold): self
    {
        if ($threshold < 0) {
            throw new InvalidArgumentException('Stock threshold cannot be negative.');
        }

        $this->threshold = $threshold;

        return $this;
    }

    /**
     * Configure the check to fail instead of warn on low stock.
     */
    public function failOnLowStock(bool $fail = true): self
    {
        $this->failOnLowStock = $fail;

        return $this;
    }

    /**
     * Perform the health check.
     */
    protected function performCheck(): Result
    {
        $counts = app(StockHealth::class)->counts($this->threshold);
        $lowStockCount = $counts['low_stock'];
        $outOfStockCount = $counts['out_of_stock'];

        if ($outOfStockCount > 0) {
            $message = "{$outOfStockCount} items are out of stock";

            if ($lowStockCount > 0) {
                $message .= ", {$lowStockCount} items have low stock";
            }

            return $this->failOnLowStock
                ? $this->failure($message, [
                    'out_of_stock' => $outOfStockCount,
                    'low_stock' => $lowStockCount,
                    'threshold' => $this->threshold,
                ])
                : $this->warning($message, [
                    'out_of_stock' => $outOfStockCount,
                    'low_stock' => $lowStockCount,
                    'threshold' => $this->threshold,
                ]);
        }

        if ($lowStockCount > 0) {
            $message = "{$lowStockCount} items have low stock (≤{$this->threshold})";
            $meta = [
                'low_stock' => $lowStockCount,
                'threshold' => $this->threshold,
            ];

            return $this->failOnLowStock
                ? $this->failure($message, $meta)
                : $this->warning($message, $meta);
        }

        return $this->success('All inventory levels are healthy', [
            'threshold' => $this->threshold,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;

final class CommissionCalculator
{
    public function calculate(Affiliate $affiliate, int $subtotalMinor): int
    {
        // Validate inputs
        if ($subtotalMinor < 0) {
            throw new \InvalidArgumentException('Subtotal cannot be negative');
        }

        $rate = (int) $affiliate->commission_rate;

        if ($affiliate->commission_type === CommissionType::Fixed) {
            if ($rate < 0) {
                throw new \InvalidArgumentException('Fixed commission rate cannot be negative');
            }

            return $rate;
        }

        // Validate percentage-based commission
        $scale = max(1, (int) config('affiliates.currency.percentage_scale', 100));
        $maxRate = $scale * 100; // 100% in basis points

        if ($rate < 0) {
            throw new \InvalidArgumentException('Percentage commission rate cannot be negative');
        }

        if ($rate > $maxRate) {
            throw new \InvalidArgumentException("Percentage commission rate cannot exceed 100% ({$maxRate} basis points)");
        }

        $commission = (int) round(($subtotalMinor * $rate) / ($scale * 100));

        // Check for overflow
        if ($commission < 0 || $commission > PHP_INT_MAX) {
            throw new \OverflowException('Commission calculation resulted in overflow');
        }

        return $commission;
    }
}

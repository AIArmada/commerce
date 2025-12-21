<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;

final class CommissionCalculator
{
    public function calculate(Affiliate $affiliate, int $subtotalMinor): int
    {
        if ($affiliate->commission_type === CommissionType::Fixed) {
            return max(0, (int) $affiliate->commission_rate);
        }

        $scale = max(1, (int) config('affiliates.currency.percentage_scale', 100));
        $rate = (int) $affiliate->commission_rate;

        $result = ($subtotalMinor * $rate) / ($scale * 100);

        if ($result > PHP_INT_MAX || $result < 0) {
            return $result > 0 ? PHP_INT_MAX : 0;
        }

        return (int) max(0, round($result));
    }
}

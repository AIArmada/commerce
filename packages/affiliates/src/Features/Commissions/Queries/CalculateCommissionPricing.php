<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Queries;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionPricing;

final class CalculateCommissionPricing implements CommissionPricing
{
    public function calculateBase(CommissionType $type, int $rate, int $subtotalMinor): int
    {
        if ($type === CommissionType::Fixed) {
            return $this->clamp($rate);
        }

        $scale = max(1, (int) config('affiliates.currency.percentage_scale', 100));

        return $this->clamp((int) round(($subtotalMinor * $rate) / ($scale * 100)));
    }

    public function clamp(int $amountMinor): int
    {
        // Zero means no commission was earned (unmatched rule, zero rate,
        // zero portion). The minimum floors earned commissions; it must not
        // conjure one from nothing.
        if ($amountMinor === 0) {
            return 0;
        }

        $clamped = max(0, $amountMinor);
        $minimum = max(0, (int) config('affiliates.commissions.minimum_minor', 0));
        $maximum = config('affiliates.commissions.maximum_minor');

        if ($clamped < $minimum) {
            return $minimum;
        }

        if ($maximum !== null && $clamped > (int) $maximum) {
            return max($minimum, (int) $maximum);
        }

        return $clamped;
    }
}

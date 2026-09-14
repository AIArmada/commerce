<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Commissions;

/**
 * Central commission clamp applied to every commission path.
 *
 * Engine, calculator, voucher-override, and explicit payload commissions all
 * funnel through here so minimum/maximum configuration cannot be bypassed.
 */
final class CommissionCaps
{
    public static function clamp(int $amountMinor): int
    {
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

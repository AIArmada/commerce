<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

/**
 * Per-currency payout minimums in minor units.
 *
 * A single global minimum cannot serve every currency: 5,000 minor units
 * means something different in MYR and USD. Balances inherit the mapped
 * minimum for their own currency and fall back to the global default.
 */
final class PayoutMinimums
{
    public static function forCurrency(string $currency): int
    {
        $code = mb_strtoupper(mb_trim($currency));

        /** @var array<string, mixed> $map */
        $map = config('affiliates.payouts.minimum_amounts_by_currency', []);

        foreach ($map as $key => $value) {
            if (mb_strtoupper((string) $key) === $code && is_numeric($value)) {
                return (int) $value;
            }
        }

        return (int) config('affiliates.payouts.minimum_amount', 5000);
    }
}

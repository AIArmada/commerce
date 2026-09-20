<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

use AIArmada\Affiliates\Settings\AffiliatePayoutSettings;
use Throwable;

/**
 * Per-currency payout minimums in minor units.
 *
 * A single global minimum cannot serve every currency: 5,000 minor units
 * means something different in MYR and USD. Balances inherit the mapped
 * minimum for their own currency and fall back to the global default.
 *
 * Values come from the `affiliate-payouts` settings group (managed through
 * the Filament adapter) with static config fallback when settings are
 * unmigrated or unavailable.
 */
final class PayoutMinimums
{
    public static function forCurrency(string $currency): int
    {
        $code = mb_strtoupper(mb_trim($currency));

        foreach (self::minimumsMap() as $key => $value) {
            if (mb_strtoupper((string) $key) === $code && is_numeric($value)) {
                return (int) $value;
            }
        }

        return self::globalMinimum();
    }

    /**
     * @return array<string, mixed>
     */
    private static function minimumsMap(): array
    {
        try {
            return app(AffiliatePayoutSettings::class)->minimumAmountsByCurrency;
        } catch (Throwable) {
            /** @var array<string, mixed> $map */
            $map = config('affiliates.payouts.minimum_amounts_by_currency', []);

            return $map;
        }
    }

    private static function globalMinimum(): int
    {
        try {
            return app(AffiliatePayoutSettings::class)->minimumAmount;
        } catch (Throwable) {
            return (int) config('affiliates.payouts.minimum_amount', 5000);
        }
    }
}

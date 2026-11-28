<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\CommerceSupport\Support\MoneyFormatter;

final class BillingCurrency
{
    /** @param array<string, float|int> $rates */
    public static function convert(int $amount, string $source, string $target, array $rates): int
    {
        if ($source === $target) {
            return $amount;
        }

        $sourceRate = $rates[$source] ?? null;
        $targetRate = $rates[$target] ?? null;

        if (! is_numeric($sourceRate) || ! is_numeric($targetRate) || $sourceRate <= 0 || $targetRate <= 0) {
            return 0;
        }

        $scale = 10 ** (MoneyFormatter::precisionFor($target) - MoneyFormatter::precisionFor($source));

        return (int) round($amount * $targetRate / $sourceRate * $scale);
    }

    /** @param array<string, int> $amounts
     * @param  array<string, float|int>  $rates
     */
    public static function total(array $amounts, string $base, bool $convert, array $rates): int
    {
        $total = $amounts[$base] ?? 0;

        if ($convert) {
            foreach ($amounts as $currency => $amount) {
                if ($currency !== $base) {
                    $total += self::convert($amount, $currency, $base, $rates);
                }
            }
        }

        return $total;
    }

    public static function major(int $amount, string $currency): float
    {
        return $amount / (10 ** MoneyFormatter::precisionFor($currency));
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use DateTimeInterface;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Revenue volume measured in one reference currency for threshold decisions.
 *
 * Volume tiers, growth bonuses, rank metrics, and program eligibility all
 * compare earned revenue against configured thresholds. Measuring blends
 * minor units across currencies silently, so every comparison funnels
 * through here: legs convert to the reference currency when rates exist,
 * otherwise only the reference-currency leg counts. Money is never summed
 * across currencies and never invented from missing rates.
 */
final class RevenueVolume
{
    /**
     * Fold grouped aggregate rows into per-currency minor-unit totals.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return array<string, int>
     */
    public static function foldRows(Collection $rows, string $fallback, string $currencyKey = 'currency', string $amountKey = 'total'): array
    {
        $byCurrency = [];

        foreach ($rows as $row) {
            $code = $row->{$currencyKey} ?? null;
            $amount = $row->{$amountKey} ?? 0;

            if (! is_string($code) || mb_trim($code) === '') {
                $code = $fallback;
            } else {
                $code = mb_strtoupper(mb_trim($code));
            }

            $byCurrency[$code] = ($byCurrency[$code] ?? 0) + (int) $amount;
        }

        return $byCurrency;
    }

    /**
     * Volume measurable in the reference currency: the converted total when
     * every leg has a rate, otherwise the reference-currency leg alone.
     *
     * @param  array<string, int>  $byCurrency
     */
    public static function measurableIn(array $byCurrency, string $reference, ?DateTimeInterface $asOf = null): int
    {
        if ($byCurrency === []) {
            return 0;
        }

        $converted = app(CurrencyConverter::class)->totalMinor($byCurrency, $reference, $asOf);

        if ($converted !== null) {
            return $converted;
        }

        return $byCurrency[mb_strtoupper($reference)] ?? 0;
    }

    public static function referenceFor(?Affiliate $affiliate): string
    {
        if ($affiliate !== null && is_string($affiliate->currency) && mb_trim($affiliate->currency) !== '') {
            return mb_strtoupper(mb_trim($affiliate->currency));
        }

        return self::default();
    }

    public static function default(): string
    {
        return mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
    }
}

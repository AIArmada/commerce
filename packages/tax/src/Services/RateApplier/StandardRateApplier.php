<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\RateApplier;

use Illuminate\Database\Eloquent\Collection;

final class StandardRateApplier implements TaxRateApplierInterface
{
    private bool $roundPerRate;

    public function __construct(?bool $roundPerRate = null)
    {
        $this->roundPerRate = $roundPerRate ?? (bool) config('tax.defaults.round_per_rate', true);
    }

    public function apply(int $amountInCents, Collection $rates, bool $pricesIncludeTax): array
    {
        $breakdown = [];
        $totalTax = 0;
        $unroundedTax = 0.0;

        $nonCompoundRates = $rates->where('is_compound', false);
        $compoundRates = $rates->where('is_compound', true);

        $netAmount = (float) $amountInCents;

        if ($pricesIncludeTax) {
            $multiplier = 1 + $nonCompoundRates->sum('rate') / 10000;

            foreach ($compoundRates as $rate) {
                $multiplier *= 1 + $rate->getRateDecimal();
            }

            $netAmount /= $multiplier;
        }

        foreach ($nonCompoundRates->concat($compoundRates) as $rate) {
            $base = $rate->is_compound
                ? $netAmount + ($this->roundPerRate && ! $pricesIncludeTax ? $totalTax : $unroundedTax)
                : $netAmount;

            $rawTax = $base * $rate->getRateDecimal();
            $unroundedTax += $rawTax;

            $taxAmount = $this->roundPerRate
                ? (int) round($rawTax)
                : (int) round($unroundedTax) - $totalTax;

            if ($pricesIncludeTax) {
                $remaining = $amountInCents - $totalTax;
                $taxAmount = $amountInCents >= 0
                    ? min($taxAmount, $remaining)
                    : max($taxAmount, $remaining);
            }

            $totalTax += $taxAmount;
            $breakdown[] = [
                'name' => $rate->name,
                'rate' => $rate->rate,
                'amount' => $taxAmount,
                'is_compound' => $rate->is_compound,
            ];
        }

        return [
            'total' => $totalTax,
            'primary_rate' => $rates->first(),
            'breakdown' => $breakdown,
        ];
    }
}

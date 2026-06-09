<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Concerns;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;

trait InteractsWithCashierChipData
{
    protected function subscriptionModel(): string
    {
        /** @var class-string<Subscription> */
        return Cashier::$subscriptionModel;
    }

    protected function formatCurrency(int $amount): string
    {
        $currency = config('cashier-chip.currency', 'MYR');
        $precision = (int) config('filament-cashier-chip.tables.amount_precision', 2);

        return mb_strtoupper($currency) . ' ' . number_format($amount / 100, $precision, '.', ',');
    }

    protected function normalizeToMonthly(int $amount, string $interval, int $count): int
    {
        $multiplier = match ($interval) {
            'day' => 30 / $count,
            'week' => 4.33 / $count,
            'month' => 1 / $count,
            'year' => 1 / (12 * $count),
            default => 1,
        };

        return (int) round($amount * $multiplier);
    }

    protected function currency(): string
    {
        return config('cashier-chip.currency', 'MYR');
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Concerns;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithCashierChipData
{
    protected function subscriptionModel(): string
    {
        /** @var class-string<Subscription> */
        return Cashier::$subscriptionModel;
    }

    /**
     * @return Builder<Subscription>
     */
    protected function subscriptionQuery(): Builder
    {
        $model = $this->subscriptionModel();

        /** @var Builder<Subscription> $query */
        $query = $model::query();

        return OwnerUiScope::apply($query, includeGlobal: false);
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

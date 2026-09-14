<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Concerns;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
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

    protected static function hasWidgetOwnerContext(): bool
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', false)) {
            return true;
        }

        return OwnerContext::resolve() !== null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function rememberWidgetValue(string $key, callable $callback): mixed
    {
        return OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-cashier-chip.widget.' . $key,
            (int) config('filament-cashier-chip.widgets.cache_ttl', 120),
            $callback,
        );
    }

    protected function formatCurrency(int $amount): string
    {
        $currency = config('cashier-chip.currency', 'MYR');
        $precision = (int) config('filament-cashier-chip.tables.amount_precision', 2);

        return mb_strtoupper($currency) . ' ' . MoneyFormatter::decimalFromMinor($amount, $currency, $precision);
    }

    protected function normalizeToMonthly(int $amount, string $interval, int $count): int
    {
        $count = max(1, $count);

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

    protected function safeCurrencyCode(): string
    {
        $currency = mb_strtoupper((string) $this->currency());

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'MYR';
    }
}

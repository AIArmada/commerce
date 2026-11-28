<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\Cashier\Queries\BillingAnalyticsQuery;
use AIArmada\Cashier\Support\BillingCurrency;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TotalMrrWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $summary = $this->getActiveSubscriptionsSummary();

        $baseCurrency = (string) config('filament-cashier.currency.base', 'MYR');
        $primaryMrr = BillingCurrency::total(
            $summary['mrrByCurrency'],
            $baseCurrency,
            (bool) config('filament-cashier.currency.display_converted', false),
            (array) config('filament-cashier.currency.conversion_rates', []),
        );
        $formattedMrr = $this->formatCurrency($primaryMrr, $baseCurrency);

        return [
            Stat::make(__('filament-cashier::dashboard.widgets.total_mrr.label'), $formattedMrr)
                ->description(__('filament-cashier::dashboard.widgets.total_mrr.description'))
                ->color('success'),
        ];
    }

    /** @return array{count: int, mrrByCurrency: array<string, int>} */
    protected function getActiveSubscriptionsSummary(): array
    {
        $summary = app(BillingAnalyticsQuery::class)->activeSummary();

        return ['count' => $summary['count'], 'mrrByCurrency' => $summary['mrrByCurrency']];
    }

    protected function formatCurrency(int $amountInCents, string $currency): string
    {
        return MoneyFormatter::formatMinor($amountInCents, $currency);
    }
}

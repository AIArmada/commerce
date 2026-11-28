<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\Cashier\Queries\BillingAnalyticsQuery;
use AIArmada\Cashier\Support\GatewayDetector;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TotalSubscribersWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    /**
     * @var array<int, Stat>|null
     */
    protected ?array $stats = null;

    /**
     * Get subscriber counts, memoized on the widget instance (request-bound)
     * instead of once() so owner switches within a long-lived process never
     * leak cached totals.
     */
    protected function getStats(): array
    {
        if ($this->stats === null) {
            $detector = app(GatewayDetector::class);
            $totals = app(BillingAnalyticsQuery::class)->activeSummary()['countByGateway'];

            $total = array_sum($totals);

            // Build description showing breakdown
            $breakdown = collect($totals)
                ->filter()
                ->map(fn ($count, $gateway) => $detector->getLabel($gateway) . ': ' . $count)
                ->join(' | ');

            $this->stats = [
                Stat::make(__('filament-cashier::dashboard.widgets.total_subscribers.label'), number_format($total))
                    ->description($breakdown ?: __('filament-cashier::dashboard.widgets.total_subscribers.description'))
                    ->descriptionIcon('heroicon-m-users')
                    ->color('primary'),
            ];
        }

        return $this->stats;
    }
}

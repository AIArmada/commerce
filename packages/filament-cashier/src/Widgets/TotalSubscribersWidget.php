<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Cashier\Subscription;

final class TotalSubscribersWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    /**
     * Get subscriber counts with once() caching.
     *
     * Caches the result for the current request to avoid redundant
     * database queries during the widget render cycle.
     */
    protected function getStats(): array
    {
        return once(function (): array {
            $detector = app(GatewayDetector::class);
            $totals = [];

            // Count Stripe subscribers
            if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
                $totals['stripe'] = CashierOwnerScope::apply(Subscription::query())
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    })
                    ->count();
            }

            // Count CHIP subscribers
            if ($detector->isAvailable('chip')) {
                $subscriptionModel = CashierChip::$subscriptionModel;
                $totals['chip'] = CashierOwnerScope::apply($subscriptionModel::query())
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    })
                    ->count();
            }

            $total = array_sum($totals);

            // Build description showing breakdown
            $breakdown = collect($totals)
                ->filter()
                ->map(fn ($count, $gateway) => $detector->getLabel($gateway) . ': ' . $count)
                ->join(' | ');

            return [
                Stat::make(__('filament-cashier::dashboard.widgets.total_subscribers.label'), number_format($total))
                    ->description($breakdown ?: __('filament-cashier::dashboard.widgets.total_subscribers.description'))
                    ->descriptionIcon('heroicon-m-users')
                    ->chart([3, 4, 3, 5, 4, 5, 6, 7])
                    ->color('primary'),
            ];
        });
    }
}

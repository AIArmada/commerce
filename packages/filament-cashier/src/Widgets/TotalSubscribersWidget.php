<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TotalSubscribersWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $detector = app(GatewayDetector::class);
        $totals = [];

        // Count Stripe subscribers
        if ($detector->isAvailable('stripe') && class_exists(\Laravel\Cashier\Subscription::class)) {
            $totals['stripe'] = \Laravel\Cashier\Subscription::query()
                ->where(function ($query): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->count();
        }

        // Count CHIP subscribers
        if ($detector->isAvailable('chip') && class_exists(\AIArmada\CashierChip\Models\Subscription::class)) {
            $totals['chip'] = \AIArmada\CashierChip\Models\Subscription::query()
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
            ->map(fn ($count, $gateway) => $detector->getLabel($gateway).': '.$count)
            ->join(' | ');

        return [
            Stat::make(__('filament-cashier::dashboard.widgets.total_subscribers.label'), number_format($total))
                ->description($breakdown ?: __('filament-cashier::dashboard.widgets.total_subscribers.description'))
                ->descriptionIcon('heroicon-m-users')
                ->chart([3, 4, 3, 5, 4, 5, 6, 7])
                ->color('primary'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Widgets;

use AIArmada\FilamentStock\Services\StockStatsAggregator;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StockStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $aggregator = app(StockStatsAggregator::class);
        $overview = $aggregator->overview();
        $periodStats = $aggregator->transactionStats(30);

        return [
            Stat::make('Total Transactions', $overview['total_transactions'])
                ->description('All stock movements')
                ->descriptionIcon(Heroicon::Cube)
                ->color('primary'),

            Stat::make('Inbound (30d)', $periodStats['inbound'])
                ->description('Stock received')
                ->descriptionIcon(Heroicon::ArrowUp)
                ->color('success'),

            Stat::make('Outbound (30d)', $periodStats['outbound'])
                ->description('Stock dispatched')
                ->descriptionIcon(Heroicon::ArrowDown)
                ->color('danger'),

            Stat::make('Net Change (30d)', $this->formatNetChange($periodStats['net_change']))
                ->description('Balance change')
                ->descriptionIcon(Heroicon::Scale)
                ->color($periodStats['net_change'] >= 0 ? 'success' : 'danger'),

            Stat::make('Active Reservations', $overview['active_reservations'])
                ->description("{$overview['total_reserved_quantity']} units reserved")
                ->descriptionIcon(Heroicon::Clock)
                ->color($overview['active_reservations'] > 0 ? 'warning' : 'success'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }

    private function formatNetChange(int $change): string
    {
        if ($change > 0) {
            return "+{$change}";
        }

        return (string) $change;
    }
}

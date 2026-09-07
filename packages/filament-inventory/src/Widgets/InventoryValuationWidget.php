<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Services\Costing\ValuationService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class InventoryValuationWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return config('filament-inventory.features.valuation_widget', true);
    }

    protected function getStats(): array
    {
        $valuationService = app(ValuationService::class);
        $method = CostingMethod::tryFrom(config('filament-inventory.defaults.costing_method', 'fifo'))
            ?? CostingMethod::Fifo;

        $valuation = $valuationService->getTotalValuation($method);

        $currency = config('inventory.defaults.currency', 'MYR');
        $totalValueMinor = $valuation['total_value'];
        /**
         * ValuationService returns integer minor units; average cost rounds half-up
         * because a fractional minor unit cannot be represented in money storage.
         */
        $averageUnitCostMinor = $valuation['total_quantity'] > 0
            ? (int) round($valuation['total_value'] / $valuation['total_quantity'], 0, PHP_ROUND_HALF_UP)
            : 0;

        return [
            Stat::make('Total Inventory Value', MoneyFormatter::formatMinor($totalValueMinor, $currency))
                ->description("Using {$method->shortLabel()} method")
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Total Units', number_format($valuation['total_quantity']))
                ->description('Across all locations')
                ->icon('heroicon-o-cube')
                ->color('info'),

            Stat::make('Unique SKUs', number_format($valuation['sku_count']))
                ->description('Products tracked')
                ->icon('heroicon-o-tag')
                ->color('primary'),

            Stat::make('Avg Unit Cost', MoneyFormatter::formatMinor($averageUnitCostMinor, $currency))
                ->description('Weighted average')
                ->icon('heroicon-o-calculator')
                ->color('warning'),
        ];
    }
}

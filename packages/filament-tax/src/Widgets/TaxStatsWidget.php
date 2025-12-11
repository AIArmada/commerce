<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaxStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $activeZones = TaxZone::active()->count();
        $activeRates = TaxRate::active()->count();
        $taxClasses = TaxClass::active()->count();

        return [
            Stat::make('Tax Zones', number_format($activeZones))
                ->description('Active zones')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Tax Rates', number_format($activeRates))
                ->description('Configured rates')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make('Tax Classes', number_format($taxClasses))
                ->description('Product categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning'),
        ];
    }
}

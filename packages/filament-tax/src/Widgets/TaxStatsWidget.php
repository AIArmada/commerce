<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Support\TaxOwnerScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

final class TaxStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stats = $this->getAggregatedStats();

        return [
            Stat::make('Tax Zones', number_format($stats['zones']))
                ->description('Active zones')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Tax Rates', number_format($stats['rates']))
                ->description('Configured rates')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make('Tax Classes', number_format($stats['classes']))
                ->description('Product categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning'),

            Stat::make('Active Exemptions', number_format($stats['exemptions']))
                ->description('Approved & valid')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('gray'),
        ];
    }

    /**
     * Fetch all stats in a single optimized query.
     * All queries are scoped by owner via TaxOwnerScope to prevent cross-tenant data leakage.
     *
     * @return array{zones: int, rates: int, classes: int, exemptions: int}
     */
    private function getAggregatedStats(): array
    {
        $now = now();

        // All queries scoped by owner to ensure multitenancy safety
        $zoneCount = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
            ->where('is_active', 1)
            ->count();

        $rateCount = TaxOwnerScope::applyToOwnedQuery(TaxRate::query())
            ->where('is_active', 1)
            ->count();

        $classCount = TaxOwnerScope::applyToOwnedQuery(TaxClass::query())
            ->where('is_active', 1)
            ->count();

        $exemptionCount = TaxOwnerScope::applyToOwnedQuery(TaxExemption::query())
            ->where('status', 'approved')
            ->where(function (Builder $builder) use ($now): void {
                $builder
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            })
            ->where(function (Builder $builder) use ($now): void {
                $builder
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->count();

        return [
            'zones' => $zoneCount,
            'rates' => $rateCount,
            'classes' => $classCount,
            'exemptions' => $exemptionCount,
        ];
    }
}

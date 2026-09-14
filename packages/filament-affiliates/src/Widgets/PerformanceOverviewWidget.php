<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

final class PerformanceOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false)
            ? OwnerContext::resolve()
            : null;

        $stats = OwnerCache::remember($owner, 'filament-affiliates.performance-overview', 60, fn (): array => $this->computeStats($owner));

        return [
            Stat::make('Conversions This Month', Number::format($stats['this_month_conversions']))
                ->description($this->getChangeDescription($stats['this_month_conversions'], $stats['last_month_conversions']))
                ->descriptionIcon($stats['this_month_conversions'] >= $stats['last_month_conversions'] ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['this_month_conversions'] >= $stats['last_month_conversions'] ? 'success' : 'danger'),

            Stat::make('Revenue This Month', $this->formatMoney($stats['this_month_revenue']))
                ->description($this->getChangeDescription($stats['this_month_revenue'], $stats['last_month_revenue']))
                ->descriptionIcon($stats['this_month_revenue'] >= $stats['last_month_revenue'] ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['this_month_revenue'] >= $stats['last_month_revenue'] ? 'success' : 'danger'),

            Stat::make('Commission Earned', $this->formatMoney($stats['this_month_commission']))
                ->description('This month'),

            Stat::make('Active Affiliates', Number::format($stats['active_affiliates']))
                ->description("{$stats['new_affiliates']} joined this month")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),
        ];
    }

    /**
     * @return array{
     *     this_month_conversions:int,
     *     this_month_revenue:int,
     *     this_month_commission:int,
     *     last_month_conversions:int,
     *     last_month_revenue:int,
     *     active_affiliates:int,
     *     new_affiliates:int,
     * }
     */
    private function computeStats(?Model $owner): array
    {
        $now = CarbonImmutable::now()->toImmutable();
        $startOfMonth = $now->startOfMonth();
        $lastMonthStart = $now->subMonth()->startOfMonth();
        $lastMonthEnd = $lastMonthStart->endOfMonth();

        $baseConversionQuery = fn () => AffiliateConversion::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            );

        $thisMonth = $baseConversionQuery()
            ->toBase()
            ->where('occurred_at', '>=', $startOfMonth)
            ->selectRaw('COUNT(*) as conversions')
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(value_minor, 0), 0)), 0) as revenue')
            ->selectRaw('COALESCE(SUM(commission_minor), 0) as commission')
            ->first();

        $lastMonth = $baseConversionQuery()
            ->toBase()
            ->whereBetween('occurred_at', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('COUNT(*) as conversions')
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(value_minor, 0), 0)), 0) as revenue')
            ->first();

        $activeStatus = AffiliateStatus::fromString(Active::class)->getValue();

        $affiliates = Affiliate::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->toBase()
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active', [$activeStatus])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as monthly_new', [$startOfMonth])
            ->first();

        return [
            'this_month_conversions' => (int) ($thisMonth->conversions ?? 0),
            'this_month_revenue' => (int) ($thisMonth->revenue ?? 0),
            'this_month_commission' => (int) ($thisMonth->commission ?? 0),
            'last_month_conversions' => (int) ($lastMonth->conversions ?? 0),
            'last_month_revenue' => (int) ($lastMonth->revenue ?? 0),
            'active_affiliates' => (int) ($affiliates->active ?? 0),
            'new_affiliates' => (int) ($affiliates->monthly_new ?? 0),
        ];
    }

    private function getChangeDescription(int | float $current, int | float $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $sign = $change >= 0 ? '+' : '';

        return $sign . Number::format($change, precision: 1) . '% from last month';
    }

    private function formatMoney(int $amountMinor): string
    {
        return MoneyFormatter::formatMinor($amountMinor, config('affiliates.currency.default', 'USD'));
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\CommerceSupport\Support\CurrencyConverter;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use stdClass;

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
        $thisRevenue = $stats['this_month_revenue'];
        $lastRevenue = $stats['last_month_revenue'];
        $revenueKnown = $thisRevenue !== null && $lastRevenue !== null;
        $revenueTrendUp = $revenueKnown && $thisRevenue >= $lastRevenue;
        $revenueDescription = $thisRevenue !== null && $lastRevenue !== null
            ? $this->getChangeDescription($thisRevenue, $lastRevenue)
            : 'Mixed currencies — set exchange rates';

        return [
            Stat::make('Conversions This Month', Number::format($stats['this_month_conversions']))
                ->description($this->getChangeDescription($stats['this_month_conversions'], $stats['last_month_conversions']))
                ->descriptionIcon($stats['this_month_conversions'] >= $stats['last_month_conversions'] ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['this_month_conversions'] >= $stats['last_month_conversions'] ? 'success' : 'danger'),

            Stat::make('Revenue This Month', $this->formatMoney($thisRevenue, $stats['currency']))
                ->description($revenueDescription)
                ->descriptionIcon(! $revenueKnown ? 'heroicon-m-minus' : ($revenueTrendUp ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'))
                ->color(! $revenueKnown ? 'gray' : ($revenueTrendUp ? 'success' : 'danger')),

            Stat::make('Commission Earned', $this->formatMoney($stats['this_month_commission'], $stats['currency']))
                ->description($stats['converted'] ? 'This month (converted to ' . $stats['currency'] . ')' : 'This month'),

            Stat::make('Active Affiliates', Number::format($stats['active_affiliates']))
                ->description("{$stats['new_affiliates']} joined this month")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),
        ];
    }

    /**
     * Money groups by currency per month. Single-currency months in the same
     * currency pass raw sums through; anything mixed converts to the package
     * default, or nulls when a rate is missing so trends never compare blends.
     *
     * @return array{
     *     this_month_conversions:int,
     *     this_month_revenue:int|null,
     *     this_month_commission:int|null,
     *     last_month_conversions:int,
     *     last_month_revenue:int|null,
     *     active_affiliates:int,
     *     new_affiliates:int,
     *     currency:string,
     *     converted:bool,
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

        $thisMonthRows = $baseConversionQuery()
            ->toBase()
            ->where('occurred_at', '>=', $startOfMonth)
            ->selectRaw('commission_currency as currency, COUNT(*) as conversions')
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(value_minor, 0), 0)), 0) as revenue')
            ->selectRaw('COALESCE(SUM(commission_minor), 0) as commission')
            ->groupBy('commission_currency')
            ->get();

        $lastMonthRows = $baseConversionQuery()
            ->toBase()
            ->whereBetween('occurred_at', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('commission_currency as currency, COUNT(*) as conversions')
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(value_minor, 0), 0)), 0) as revenue')
            ->groupBy('commission_currency')
            ->get();

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

        $thisMonth = $this->foldMonth($thisMonthRows);
        $lastMonth = $this->foldMonth($lastMonthRows);
        $money = $this->summarizeMonths($thisMonth, $lastMonth);

        return [
            'this_month_conversions' => $thisMonth['conversions'],
            'this_month_revenue' => $money['this_revenue'],
            'this_month_commission' => $money['this_commission'],
            'last_month_conversions' => $lastMonth['conversions'],
            'last_month_revenue' => $money['last_revenue'],
            'active_affiliates' => (int) ($affiliates->active ?? 0),
            'new_affiliates' => (int) ($affiliates->monthly_new ?? 0),
            'currency' => $money['currency'],
            'converted' => $money['converted'],
        ];
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return array{conversions: int, by_currency: array<string, array{revenue: int, commission: int}>}
     */
    private function foldMonth(Collection $rows): array
    {
        $conversions = 0;
        $byCurrency = [];

        foreach ($rows as $row) {
            $currency = is_string($row->currency) && mb_trim($row->currency) !== ''
                ? mb_strtoupper(mb_trim($row->currency))
                : $this->displayCurrency();

            $byCurrency[$currency] ??= ['revenue' => 0, 'commission' => 0];
            $byCurrency[$currency]['revenue'] += (int) $row->revenue;
            $byCurrency[$currency]['commission'] += (int) ($row->commission ?? 0);
            $conversions += (int) $row->conversions;
        }

        return ['conversions' => $conversions, 'by_currency' => $byCurrency];
    }

    /**
     * @param  array{conversions: int, by_currency: array<string, array{revenue: int, commission: int}>}  $thisMonth
     * @param  array{conversions: int, by_currency: array<string, array{revenue: int, commission: int}>}  $lastMonth
     * @return array{this_revenue: int|null, this_commission: int|null, last_revenue: int|null, currency: string, converted: bool}
     */
    private function summarizeMonths(array $thisMonth, array $lastMonth): array
    {
        $currencies = array_unique([
            ...array_keys($thisMonth['by_currency']),
            ...array_keys($lastMonth['by_currency']),
        ]);

        if (count($currencies) === 1) {
            $only = $currencies[0];

            return [
                'this_revenue' => $thisMonth['by_currency'][$only]['revenue'] ?? 0,
                'this_commission' => $thisMonth['by_currency'][$only]['commission'] ?? 0,
                'last_revenue' => $lastMonth['by_currency'][$only]['revenue'] ?? 0,
                'currency' => $only,
                'converted' => false,
            ];
        }

        $display = $this->displayCurrency();
        $converter = app(CurrencyConverter::class);

        $thisRevenue = [];
        $thisCommission = [];
        $lastRevenue = [];

        foreach ($thisMonth['by_currency'] as $currency => $money) {
            $thisRevenue[$currency] = $money['revenue'];
            $thisCommission[$currency] = $money['commission'];
        }

        foreach ($lastMonth['by_currency'] as $currency => $money) {
            $lastRevenue[$currency] = $money['revenue'];
        }

        $thisRevenueTotal = $converter->totalMinor($thisRevenue, $display);
        $thisCommissionTotal = $converter->totalMinor($thisCommission, $display);
        $lastRevenueTotal = $converter->totalMinor($lastRevenue, $display);

        return [
            'this_revenue' => $thisRevenueTotal,
            'this_commission' => $thisCommissionTotal,
            'last_revenue' => $lastRevenueTotal,
            'currency' => $display,
            'converted' => $thisRevenueTotal !== null && $thisCommissionTotal !== null && $lastRevenueTotal !== null
                && ($thisRevenue !== [] || $lastRevenue !== []),
        ];
    }

    private function displayCurrency(): string
    {
        return mb_strtoupper((string) config('affiliates.currency.default', 'USD'));
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

    private function formatMoney(?int $amountMinor, string $currency): string
    {
        if ($amountMinor === null) {
            return '—';
        }

        return MoneyFormatter::formatMinor($amountMinor, $currency);
    }
}

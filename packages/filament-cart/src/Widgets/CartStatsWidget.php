<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\Cart\Snapshots\CartSnapshot as Cart;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\CommerceSupport\Support\OwnerCache;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

final class CartStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $owner = Cart::resolveCurrentOwner();

        /** @var array<int, Stat> $stats */
        $stats = OwnerCache::remember($owner, 'filament-cart.stats', 60, fn (): array => $this->buildStats());

        return $stats;
    }

    /**
     * @return array<int, Stat>
     */
    private function buildStats(): array
    {
        $base = Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords());
        $recentCutoff = CarbonImmutable::now()->subMinutes(30);
        $highValueThreshold = (int) config('cart.snapshots.analytics.high_value_threshold_minor', 10000);
        $yesterday = CarbonImmutable::now()->subDay();

        $counts = (clone $base)
            ->toBase()
            ->selectRaw(
                'SUM(CASE WHEN last_activity_at >= ? THEN 1 ELSE 0 END) AS active_carts',
                [$recentCutoff],
            )
            ->selectRaw(
                'SUM(CASE WHEN items_count > ? AND last_activity_at >= ? THEN 1 ELSE 0 END) AS carts_with_items',
                [0, $recentCutoff],
            )
            ->selectRaw(
                'SUM(CASE WHEN checkout_started_at IS NOT NULL AND checkout_abandoned_at IS NULL THEN 1 ELSE 0 END) AS checkouts_in_progress',
            )
            ->selectRaw(
                'SUM(CASE WHEN checkout_abandoned_at >= ? THEN 1 ELSE 0 END) AS recent_abandonments',
                [$recentCutoff],
            )
            ->selectRaw(
                'SUM(CASE WHEN total >= ? THEN 1 ELSE 0 END) AS high_value_carts',
                [$highValueThreshold],
            )
            ->selectRaw(
                'SUM(CASE WHEN checkout_started_at IS NOT NULL AND checkout_started_at >= ? THEN 1 ELSE 0 END) AS checkouts_started_24h',
                [$yesterday],
            )
            ->selectRaw(
                'SUM(CASE WHEN checkout_abandoned_at IS NOT NULL AND checkout_abandoned_at >= ? THEN 1 ELSE 0 END) AS abandoned_24h',
                [$yesterday],
            )
            ->first();

        $counts = (array) ($counts ?? []);

        $totalsByCurrency = (clone $base)
            ->where('items_count', '>', 0)
            ->selectRaw('currency, SUM(total) as total_sum')
            ->groupBy('currency')
            ->orderByDesc('total_sum')
            ->get();

        $activeCarts = (int) ($counts['active_carts'] ?? 0);
        $cartsWithItems = (int) ($counts['carts_with_items'] ?? 0);
        $checkoutsInProgress = (int) ($counts['checkouts_in_progress'] ?? 0);
        $recentAbandonments = (int) ($counts['recent_abandonments'] ?? 0);
        $highValueCarts = (int) ($counts['high_value_carts'] ?? 0);
        $checkoutsStarted24h = (int) ($counts['checkouts_started_24h'] ?? 0);
        $abandoned24h = (int) ($counts['abandoned_24h'] ?? 0);

        return [
            Stat::make('Active Carts', (string) $activeCarts)
                ->description("{$cartsWithItems} with items")
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->chart($this->getActiveCartsChart())
                ->color('primary'),

            Stat::make('Checkouts', (string) $checkoutsInProgress)
                ->description('In progress')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('success'),

            Stat::make('Recent Abandonments', (string) $recentAbandonments)
                ->description($this->getAbandonmentRate($checkoutsStarted24h, $abandoned24h) . '% rate (24h)')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($recentAbandonments > 0 ? 'warning' : 'gray'),

            $this->totalValueStat($totalsByCurrency, $highValueCarts),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function getAbandonmentRate(int $checkoutsStarted, int $abandoned): string
    {
        if ($checkoutsStarted === 0) {
            return '0';
        }

        return number_format(($abandoned / $checkoutsStarted) * 100, 1);
    }

    /**
     * @param  Collection<int, Cart>  $totalsByCurrency
     */
    private function totalValueStat(Collection $totalsByCurrency, int $highValueCarts): Stat
    {
        return Stat::make('Total Value', $this->formatTotalValue($totalsByCurrency))
            ->description($this->totalValueDescription($totalsByCurrency, $highValueCarts))
            ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
            ->color('info');
    }

    /**
     * @param  Collection<int, Cart>  $totalsByCurrency
     */
    private function formatTotalValue(Collection $totalsByCurrency): string
    {
        $dominant = $totalsByCurrency->first();

        if (! $dominant instanceof Cart) {
            return $this->formatMoney(0);
        }

        $formatted = CartMoney::formatMinor((int) $dominant->getAttribute('total_sum'), $this->rowCurrency($dominant));
        $extraCurrencies = $totalsByCurrency->count() - 1;

        if ($extraCurrencies > 0) {
            $formatted .= " (+{$extraCurrencies})";
        }

        return $formatted;
    }

    /**
     * @param  Collection<int, Cart>  $totalsByCurrency
     */
    private function totalValueDescription(Collection $totalsByCurrency, int $highValueCarts): string
    {
        $description = "{$highValueCarts} high-value carts";

        if ($totalsByCurrency->count() > 1) {
            $breakdown = $totalsByCurrency
                ->map(fn (Cart $row): string => CartMoney::formatMinor((int) $row->getAttribute('total_sum'), $this->rowCurrency($row)))
                ->implode(' · ');

            $description .= " · {$breakdown}";
        }

        return $description;
    }

    private function rowCurrency(Cart $row): ?string
    {
        $currency = $row->getAttribute('currency');

        return is_string($currency) && $currency !== '' ? $currency : null;
    }

    /**
     * @return array<int, int>
     */
    private function getActiveCartsChart(): array
    {
        $start = CarbonImmutable::now()->subDays(6)->startOfDay();

        $countsByDay = Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords())
            ->where('items_count', '>', 0)
            ->where('updated_at', '>=', $start)
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day');

        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = (int) ($countsByDay[CarbonImmutable::now()->subDays($i)->toDateString()] ?? 0);
        }

        return $data;
    }

    private function formatMoney(int $amount): string
    {
        return CartMoney::formatMinor($amount);
    }
}

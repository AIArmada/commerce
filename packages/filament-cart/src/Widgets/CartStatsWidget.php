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

        $activeCarts = (clone $base)->where('last_activity_at', '>=', $recentCutoff)->count();
        $cartsWithItems = (clone $base)->where('items_count', '>', 0)->where('last_activity_at', '>=', $recentCutoff)->count();
        $checkoutsInProgress = (clone $base)->whereNotNull('checkout_started_at')->whereNull('checkout_abandoned_at')->count();
        $recentAbandonments = (clone $base)->where('checkout_abandoned_at', '>=', $recentCutoff)->count();

        $totalsByCurrency = (clone $base)
            ->where('items_count', '>', 0)
            ->selectRaw('currency, SUM(total) as total_sum')
            ->groupBy('currency')
            ->orderByDesc('total_sum')
            ->get();
        $highValueCarts = (clone $base)->where('total', '>=', $highValueThreshold)->count();

        $checkoutsStarted24h = (clone $base)
            ->whereNotNull('checkout_started_at')
            ->where('checkout_started_at', '>=', $yesterday)
            ->count();

        $abandoned24h = (clone $base)
            ->whereNotNull('checkout_abandoned_at')
            ->where('checkout_abandoned_at', '>=', $yesterday)
            ->count();

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

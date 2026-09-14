<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Support\PurchaseRevenueExpressions;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class ChipStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    private const int STATS_CACHE_TTL_SECONDS = 120;

    protected function getStats(): array
    {
        /** @var array{today: int, week: int, month: int, successRate: float} $numbers */
        $numbers = OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-chip.chip-stats',
            self::STATS_CACHE_TTL_SECONDS,
            fn (): array => $this->withResolvedOwnerOrExplicitGlobal(fn (): array => [
                'today' => $this->getTodayRevenue(),
                'week' => $this->getWeekRevenue(),
                'month' => $this->getMonthRevenue(),
                'successRate' => $this->getSuccessRate(),
            ], ['today' => 0, 'week' => 0, 'month' => 0, 'successRate' => 0.0]),
        );

        $successRate = $numbers['successRate'];

        return [
            Stat::make('Today\'s Revenue', $this->formatCurrency($numbers['today']))
                ->description('Paid purchases today')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),

            Stat::make('This Week', $this->formatCurrency($numbers['week']))
                ->description('Last 7 days')
                ->descriptionIcon(Heroicon::CalendarDays)
                ->color('primary'),

            Stat::make('This Month', $this->formatCurrency($numbers['month']))
                ->description('Current month')
                ->descriptionIcon(Heroicon::Calendar)
                ->color('info'),

            Stat::make('Success Rate', "{$successRate}%")
                ->description('Paid vs failed')
                ->descriptionIcon(Heroicon::ChartBar)
                ->color($successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger')),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function getTodayRevenue(): int
    {
        return $this->getRevenueForPeriod(CarbonImmutable::now()->startOfDay());
    }

    private function getWeekRevenue(): int
    {
        return $this->getRevenueForPeriod(CarbonImmutable::now()->subDays(7));
    }

    private function getMonthRevenue(): int
    {
        return $this->getRevenueForPeriod(CarbonImmutable::now()->startOfMonth());
    }

    private function getRevenueForPeriod(DateTimeInterface $since): int
    {
        $sinceTimestamp = $since->getTimestamp();
        $query = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('status', ['paid', 'cleared', 'settled'])
            ->where('is_test', false)
            ->where('created_on', '>=', $sinceTimestamp);

        return (int) $query->sum(DB::raw(PurchaseRevenueExpressions::totalMinor($query)));
    }

    private function getSuccessRate(): float
    {
        $successStatuses = ['paid', 'cleared', 'settled'];
        $failedStatuses = ['error', 'blocked', 'cancelled', 'released', 'expired', 'chargeback'];

        $successful = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('status', $successStatuses)
            ->where('is_test', false)
            ->count();

        $failed = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('status', $failedStatuses)
            ->where('is_test', false)
            ->count();

        $total = $successful + $failed;

        if ($total === 0) {
            return 100.0;
        }

        return round(($successful / $total) * 100, 1);
    }

    private function formatCurrency(int $amountInCents): string
    {
        return MoneyFormatter::formatMinor($amountInCents, config('filament-chip.default_currency', 'MYR'));
    }

    private function withResolvedOwnerOrExplicitGlobal(callable $callback, mixed $empty = null): mixed
    {
        if (OwnerContext::resolve() !== null) {
            return $callback();
        }

        if (! OwnerContext::isExplicitGlobal()) {
            return $empty;
        }

        return OwnerContext::withOwner(null, static fn (): mixed => $callback());
    }
}

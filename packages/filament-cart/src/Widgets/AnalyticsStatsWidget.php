<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Analytics stats overview widget for the analytics page.
 */
class AnalyticsStatsWidget extends StatsOverviewWidget
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $interval = null;

    protected ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    #[On('date-range-updated')]
    public function refresh(): void
    {
        // Widget will refresh on event
    }

    protected function getStats(): array
    {
        $from = $this->resolveDateFrom();
        $to = $this->resolveDateTo();

        $service = app(CartAnalyticsService::class);
        $metrics = $service->getDashboardMetrics($from, $to);

        return [
            Stat::make('Total Carts', number_format($metrics->total_carts))
                ->description('Carts in period')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary'),

            Stat::make('Active Carts', number_format($metrics->active_carts))
                ->description('With items')
                ->icon('heroicon-o-cursor-arrow-rays')
                ->color('success'),

            Stat::make('Abandoned', number_format($metrics->abandoned_carts))
                ->description($this->formatRateWithChange(
                    $metrics->abandonment_rate,
                    $metrics->abandonment_rate_change,
                    true,
                ))
                ->descriptionIcon($this->getRateIcon($metrics->abandonment_rate_change, true))
                ->color($this->getAbandonmentColor($metrics->abandonment_rate))
                ->icon('heroicon-o-x-circle'),

            Stat::make('Recovered', number_format($metrics->recovered_carts))
                ->description($this->formatRateWithChange(
                    $metrics->recovery_rate,
                    $metrics->recovery_rate_change,
                ))
                ->descriptionIcon($this->getRateIcon($metrics->recovery_rate_change))
                ->color('success')
                ->icon('heroicon-o-arrow-path'),

            Stat::make('Conversion Rate', $this->formatPercent($metrics->conversion_rate))
                ->description($this->formatChange($metrics->conversion_rate_change))
                ->descriptionIcon($this->getRateIcon($metrics->conversion_rate_change))
                ->color($this->getConversionColor($metrics->conversion_rate))
                ->icon('heroicon-o-check-circle'),

            Stat::make('Total Value', $this->formatMoney($metrics->total_value_cents))
                ->description('Avg: ' . $this->formatMoney($metrics->average_cart_value_cents))
                ->icon('heroicon-o-currency-dollar')
                ->color('warning'),
        ];
    }

    private function resolveDateFrom(): Carbon
    {
        $value = $this->resolveQueryValue('dateFrom', $this->dateFrom);

        return $value !== '' ? Carbon::parse($value) : Carbon::now()->subDays(30);
    }

    private function resolveDateTo(): Carbon
    {
        $value = $this->resolveQueryValue('dateTo', $this->dateTo);

        return $value !== '' ? Carbon::parse($value) : Carbon::now();
    }

    private function resolveQueryValue(string $key, ?string $fallback): string
    {
        $queryValue = request()->query($key);

        if (is_string($queryValue) && $queryValue !== '') {
            return $queryValue;
        }

        return $fallback ?? '';
    }

    private function formatPercent(float $rate): string
    {
        return number_format($rate * 100, 1) . '%';
    }

    private function formatMoney(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }

    private function formatChange(?float $change): string
    {
        if ($change === null) {
            return 'No prior data';
        }

        $percent = number_format(abs($change) * 100, 1);

        return $change >= 0 ? "+{$percent}%" : "-{$percent}%";
    }

    private function formatRateWithChange(float $rate, ?float $change, bool $lowerIsBetter = false): string
    {
        $rateStr = $this->formatPercent($rate);

        if ($change === null) {
            return $rateStr;
        }

        $changeStr = $this->formatChange($change);

        return "{$rateStr} ({$changeStr})";
    }

    private function getRateIcon(?float $change, bool $lowerIsBetter = false): string
    {
        if ($change === null) {
            return 'heroicon-o-minus';
        }

        if ($lowerIsBetter) {
            return $change <= 0 ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-trending-up';
        }

        return $change >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down';
    }

    private function getAbandonmentColor(float $rate): string
    {
        if ($rate >= 0.7) {
            return 'danger';
        }

        if ($rate >= 0.5) {
            return 'warning';
        }

        return 'info';
    }

    private function getConversionColor(float $rate): string
    {
        if ($rate >= 0.05) {
            return 'success';
        }

        if ($rate >= 0.02) {
            return 'warning';
        }

        return 'danger';
    }
}

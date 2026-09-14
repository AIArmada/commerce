<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentChip\Support\PurchaseRevenueExpressions;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

final class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue Trend (Last 30 Days)';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    private const int CHART_CACHE_TTL_SECONDS = 120;

    protected function getData(): array
    {
        /** @var array{labels: array<string>, amounts: array<int>} $data */
        $data = OwnerCache::remember(
            OwnerContext::resolve(),
            'filament-chip.revenue-chart',
            self::CHART_CACHE_TTL_SECONDS,
            fn (): array => $this->getRevenueData(),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_values($data['amounts']),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => true,
                ],
            ],
            'labels' => array_values($data['labels']),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "' . config('filament-chip.default_currency', 'MYR') . ' " + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{labels: array<string>, amounts: array<int>}
     */
    private function getRevenueData(): array
    {
        return $this->withResolvedOwnerOrExplicitGlobal(function (): array {
            $labels = [];

            $startDate = CarbonImmutable::now()->subDays(29)->startOfDay();
            $endDate = CarbonImmutable::now()->endOfDay();

            for ($i = 29; $i >= 0; $i--) {
                $labels[] = CarbonImmutable::now()->subDays($i)->format('M d');
            }

            $amountsByOffset = $this->getRevenueByDayOffset($startDate, $endDate);

            $amounts = [];

            for ($offset = 0; $offset < 30; $offset++) {
                $amounts[] = (int) (($amountsByOffset[$offset] ?? 0) / 100);
            }

            return [
                'labels' => $labels,
                'amounts' => $amounts,
            ];
        }, ['labels' => [], 'amounts' => []]);
    }

    /**
     * Sum revenue per day in SQL, bucketed by whole-day offset from the
     * window start. Offset arithmetic on the unix timestamp is portable
     * across drivers and immune to display-timezone shifts.
     *
     * @return array<int, int> Day offset (0 = oldest) to minor-unit revenue.
     */
    private function getRevenueByDayOffset(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $query = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('status', ['paid', 'cleared', 'settled'])
            ->where('is_test', false)
            ->whereBetween('created_on', [$startDate->getTimestamp(), $endDate->getTimestamp()]);

        $windowStart = (int) $startDate->getTimestamp();
        $offsetSql = 'FLOOR((created_on - ' . $windowStart . ') / 86400)';
        $totalSql = PurchaseRevenueExpressions::totalMinor($query);

        $rows = $query
            ->selectRaw("{$offsetSql} AS day_offset, SUM({$totalSql}) AS day_revenue")
            ->groupByRaw($offsetSql)
            ->get();

        $amountsByOffset = [];

        foreach ($rows as $row) {
            $offset = (int) $row->getAttribute('day_offset');

            if ($offset < 0 || $offset > 29) {
                continue;
            }

            $amountsByOffset[$offset] = (int) $row->getAttribute('day_revenue');
        }

        return $amountsByOffset;
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

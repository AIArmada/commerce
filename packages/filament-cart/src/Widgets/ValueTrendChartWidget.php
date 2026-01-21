<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Cart value trend chart widget.
 */
class ValueTrendChartWidget extends ChartWidget
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $interval = null;

    protected ?string $heading = 'Cart Value Trends';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    #[On('date-range-updated')]
    public function refresh(): void
    {
        // Widget will refresh on event
    }

    protected function getData(): array
    {
        $from = $this->resolveDateFrom();
        $to = $this->resolveDateTo();
        $interval = $this->resolveInterval();

        $service = app(CartAnalyticsService::class);
        $trends = $service->getValueTrends($from, $to, $interval);

        $labels = [];
        $values = [];
        $counts = [];

        foreach ($trends as $point) {
            $labels[] = $this->formatLabel($point['date'], $interval);
            $values[] = round($point['value'] / 100, 2);
            $counts[] = $point['count'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Value ($)',
                    'data' => $values,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Cart Count',
                    'data' => $counts,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Value ($)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Count',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
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

    private function resolveInterval(): string
    {
        $value = $this->resolveQueryValue('interval', $this->interval);

        return $value !== '' ? $value : 'day';
    }

    private function resolveQueryValue(string $key, ?string $fallback): string
    {
        $queryValue = request()->query($key);

        if (is_string($queryValue) && $queryValue !== '') {
            return $queryValue;
        }

        return $fallback ?? '';
    }

    private function formatLabel(string $date, string $interval): string
    {
        return match ($interval) {
            'week' => 'Week ' . mb_substr($date, 4),
            'month' => Carbon::parse($date . '-01')->format('M Y'),
            default => Carbon::parse($date)->format('M j'),
        };
    }
}

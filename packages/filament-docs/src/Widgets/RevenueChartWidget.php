<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Widgets;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

final class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue (Last 30 Days)';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $startDate = $today->subDays(29);

        $dates = [];
        $labels = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->addDays($i);
            $dates[] = $date->format('Y-m-d');
            $labels[] = $date->format('M d');
        }

        $totalsByDate = DocsOwnerScope::applyToDocs(Doc::query())
            ->where('status', DocStatus::PAID)
            ->whereBetween('paid_at', [$startDate, $today->endOfDay()])
            ->selectRaw('DATE(paid_at) as paid_date, SUM(total) as total_sum')
            ->groupBy('paid_date')
            ->pluck('total_sum', 'paid_date')
            ->map(fn (mixed $value): float => (float) $value)
            ->all();

        $data = array_map(
            static fn (string $date): float => (float) ($totalsByDate[$date] ?? 0),
            $dates,
        );

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data,
                    'fill' => true,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
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
        $currencyPrefix = (string) config('docs.defaults.currency', 'MYR') . ' ';

        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return ' . json_encode($currencyPrefix) . ' + value.toLocaleString(); }',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}

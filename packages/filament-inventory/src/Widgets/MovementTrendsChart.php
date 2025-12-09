<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\Inventory\Reports\MovementAnalysisReport;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

final class MovementTrendsChart extends ChartWidget
{
    protected ?string $heading = 'Inventory Movement Trends';

    protected static ?int $sort = 6;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return config('filament-inventory.features.movement_trends_chart', true);
    }

    protected function getData(): array
    {
        $report = app(MovementAnalysisReport::class);
        $trends = $report->getDailyMovementTrends();

        return [
            'datasets' => [
                [
                    'label' => 'Receipts',
                    'data' => $trends->pluck('receipts')->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Shipments',
                    'data' => $trends->pluck('shipments')->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Adjustments',
                    'data' => $trends->pluck('adjustments')->toArray(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Transfers',
                    'data' => $trends->pluck('transfers')->toArray(),
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $trends->pluck('date')->map(
                fn ($date) => Carbon::parse($date)->format('M d')
            )->toArray(),
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
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Quantity',
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
}

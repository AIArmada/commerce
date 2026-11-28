<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\Shipping\Features\Reporting\Api\DashboardStats;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

class CarrierPerformanceWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public function getHeading(): string
    {
        return 'Carrier Performance';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = app(DashboardStats::class)->carrierPerformance();
        $labels = array_map(static fn (array $row): string => Str::ucfirst($row['carrier']), $rows);
        $deliveredData = array_column($rows, 'delivered');
        $inTransitData = array_column($rows, 'inTransit');
        $exceptionsData = array_column($rows, 'exceptions');

        return [
            'datasets' => [
                [
                    'label' => 'Delivered',
                    'data' => $deliveredData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                ],
                [
                    'label' => 'In Transit',
                    'data' => $inTransitData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                ],
                [
                    'label' => 'Exceptions',
                    'data' => $exceptionsData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                ],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\Inventory\Features\Stock\Queries\StockLevelReport;
use Filament\Widgets\ChartWidget;

final class AbcAnalysisChart extends ChartWidget
{
    protected ?string $heading = 'ABC Analysis';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 7;

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return config('filament-inventory.features.abc_analysis_chart', true);
    }

    public function getDescription(): string
    {
        $report = app(StockLevelReport::class);
        $analysis = $report->getAbcAnalysis();

        if ($analysis->isEmpty()) {
            return 'No valued inventory in the reporting currency.';
        }

        $classA = $analysis->where('classification', 'A');
        $classAPercent = round(($classA->count() / $analysis->count()) * 100, 1);
        $classAValuePercent = round((float) ($classA->last()['cumulative_percentage'] ?? 0), 1);

        return "Class A: {$classAPercent}% of displayed valued SKUs, {$classAValuePercent}% of total recorded value.";
    }

    protected function getData(): array
    {
        $report = app(StockLevelReport::class);
        $analysis = $report->getAbcAnalysis();

        $classACounts = $analysis->where('classification', 'A')->count();
        $classBCounts = $analysis->where('classification', 'B')->count();
        $classCCounts = $analysis->where('classification', 'C')->count();

        return [
            'datasets' => [
                [
                    'label' => 'SKU Count',
                    'data' => [$classACounts, $classBCounts, $classCCounts],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444'],
                ],
            ],
            'labels' => [
                'Class A',
                'Class B',
                'Class C',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
            ],
        ];
    }
}

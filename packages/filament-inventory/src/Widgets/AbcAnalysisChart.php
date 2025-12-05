<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\Inventory\Reports\StockLevelReport;
use Filament\Widgets\ChartWidget;

final class AbcAnalysisChart extends ChartWidget
{
    protected ?string $heading = 'ABC Analysis';

    protected static ?int $sort = 7;

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return config('filament-inventory.features.abc_analysis_chart', true);
    }

    public function getDescription(): ?string
    {
        $report = app(StockLevelReport::class);
        $analysis = $report->getAbcAnalysis();

        $classAPercent = $analysis->count() > 0
            ? round(($analysis->where('classification', 'A')->count() / $analysis->count()) * 100, 1)
            : 0;

        return "Class A items: {$classAPercent}% of SKUs, 80% of value";
    }

    protected function getData(): array
    {
        $report = app(StockLevelReport::class);
        $analysis = $report->getAbcAnalysis();

        $classACounts = $analysis->where('classification', 'A')->count();
        $classBCounts = $analysis->where('classification', 'B')->count();
        $classCCounts = $analysis->where('classification', 'C')->count();

        $classAValue = $analysis->where('classification', 'A')->sum('total_value');
        $classBValue = $analysis->where('classification', 'B')->sum('total_value');
        $classCValue = $analysis->where('classification', 'C')->sum('total_value');

        return [
            'datasets' => [
                [
                    'label' => 'SKU Count',
                    'data' => [$classACounts, $classBCounts, $classCCounts],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444'],
                ],
            ],
            'labels' => [
                'Class A (80% value)',
                'Class B (15% value)',
                'Class C (5% value)',
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

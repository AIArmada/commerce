<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

final class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue Trend (Last 30 Days)';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $data = $this->getRevenueData();

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
        $labels = [];
        $amountsByDay = [];

        $startDate = CarbonImmutable::now()->subDays(29)->startOfDay();
        $endDate = CarbonImmutable::now()->endOfDay();

        for ($i = 29; $i >= 0; $i--) {
            $date = CarbonImmutable::now()->subDays($i);
            $label = $date->format('M d');
            $labels[] = $label;
            $amountsByDay[$label] = 0;
        }

        $purchases = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->where('status', 'paid')
            ->where('is_test', false)
            ->whereBetween('created_on', [$startDate->getTimestamp(), $endDate->getTimestamp()])
            ->get(['created_on', 'purchase']);

        foreach ($purchases as $purchase) {
            $createdOn = (int) ($purchase->getRawOriginal('created_on') ?? 0);

            if ($createdOn <= 0) {
                continue;
            }

            $label = CarbonImmutable::createFromTimestamp($createdOn)->format('M d');

            if (! array_key_exists($label, $amountsByDay)) {
                continue;
            }

            $total = $purchase->purchase['total'] ?? $purchase->purchase['amount'] ?? 0;

            if (is_array($total)) {
                $amountsByDay[$label] += (int) ($total['amount'] ?? 0);
            } else {
                $amountsByDay[$label] += (int) $total;
            }
        }

        $amounts = array_map(
            static fn (int $amountInCents): int => (int) ($amountInCents / 100),
            array_values($amountsByDay)
        );

        return [
            'labels' => $labels,
            'amounts' => $amounts,
        ];
    }
}

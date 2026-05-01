<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Services\ChipCollectService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Throwable;

final class AccountTurnoverWidget extends ChartWidget
{
    protected ?string $heading = 'Account Turnover';

    protected ?string $description = 'Revenue and fees over the last 30 days';

    protected static ?int $sort = 21;

    protected ?string $maxHeight = '300px';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $data = $this->getTurnoverData();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (MYR)',
                    'data' => $data['revenue'],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Fees (MYR)',
                    'data' => $data['fees'],
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array{labels: array<string>, revenue: array<float>, fees: array<float>}
     */
    private function getTurnoverData(): array
    {
        $labels = [];
        $revenue = [];
        $fees = [];

        try {
            $service = app(ChipCollectService::class);

            for ($i = 29; $i >= 0; $i--) {
                $date = CarbonImmutable::now()->subDays($i);
                $labels[] = $date->format('M d');

                $response = $service->getAccountTurnover([
                    'date_from' => $date->startOfDay()->getTimestamp(),
                    'date_to' => $date->endOfDay()->getTimestamp(),
                ]);

                $dayRevenue = (float) ($response['total_income'] ?? $response['revenue'] ?? 0);
                $dayFees = (float) ($response['total_fees'] ?? $response['fees'] ?? 0);

                $revenue[] = round($dayRevenue / 100, 2);
                $fees[] = round($dayFees / 100, 2);
            }
        } catch (Throwable) {
            for ($i = 29; $i >= 0; $i--) {
                $date = CarbonImmutable::now()->subDays($i);
                $labels[] = $date->format('M d');
                $revenue[] = 0;
                $fees[] = 0;
            }
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'fees' => $fees,
        ];
    }
}

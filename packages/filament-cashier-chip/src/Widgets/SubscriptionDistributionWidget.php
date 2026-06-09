<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData;
use Filament\Widgets\ChartWidget;

final class SubscriptionDistributionWidget extends ChartWidget
{
    use InteractsWithCashierChipData;

    protected ?string $heading = 'Subscription Distribution';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $data = $this->getDistributionData();

        return [
            'datasets' => [
                [
                    'data' => array_values($data['counts']),
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(156, 163, 175, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                    ],
                ],
            ],
            'labels' => array_values($data['labels']),
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
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    /**
     * @return array{labels: array<string>, counts: array<int>}
     */
    private function getDistributionData(): array
    {
        $statuses = [
            Subscription::STATUS_ACTIVE => 'Active',
            Subscription::STATUS_TRIALING => 'Trialing',
            Subscription::STATUS_CANCELED => 'Canceled',
            Subscription::STATUS_PAST_DUE => 'Past Due',
            Subscription::STATUS_PAUSED => 'Paused',
            Subscription::STATUS_INCOMPLETE => 'Incomplete',
        ];

        $labels = [];
        $counts = [];

        foreach ($statuses as $status => $label) {
            $count = $this->subscriptionModel()::query()
                ->where('chip_status', $status)
                ->count();

            if ($count > 0) {
                $labels[] = $label;
                $counts[] = $count;
            }
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
        ];
    }
}

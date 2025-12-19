<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use Filament\Widgets\ChartWidget;

final class SubscriptionDistributionWidget extends ChartWidget
{
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
                        'rgba(34, 197, 94, 0.8)',   // Active - green
                        'rgba(234, 179, 8, 0.8)',   // Trialing - yellow
                        'rgba(239, 68, 68, 0.8)',   // Canceled - red
                        'rgba(249, 115, 22, 0.8)', // Past Due - orange
                        'rgba(156, 163, 175, 0.8)', // Paused - gray
                        'rgba(168, 85, 247, 0.8)', // Incomplete - purple
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
        /** @var class-string<Subscription> $subscriptionModel */
        $subscriptionModel = Cashier::$subscriptionModel;

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
            $count = CashierChipOwnerScope::apply($subscriptionModel::query())
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

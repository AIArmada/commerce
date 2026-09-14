<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData;
use Filament\Widgets\ChartWidget;
use Override;

final class SubscriptionDistributionWidget extends ChartWidget
{
    use InteractsWithCashierChipData;

    protected ?string $heading = 'Subscription Distribution';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 1;

    protected ?string $pollingInterval = '120s';

    #[Override]
    public static function canView(): bool
    {
        return static::hasWidgetOwnerContext();
    }

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
        return $this->rememberWidgetValue('distribution.data', function (): array {
            $statuses = [
                SubscriptionStatus::Active->value => 'Active',
                SubscriptionStatus::Trialing->value => 'Trialing',
                SubscriptionStatus::Canceled->value => 'Canceled',
                SubscriptionStatus::PastDue->value => 'Past Due',
                SubscriptionStatus::Paused->value => 'Paused',
                SubscriptionStatus::Incomplete->value => 'Incomplete',
            ];

            $grouped = $this->subscriptionQuery()
                ->whereIn('chip_status', array_keys($statuses))
                ->toBase()
                ->selectRaw('chip_status, COUNT(*) AS aggregate')
                ->groupBy('chip_status')
                ->pluck('aggregate', 'chip_status');

            $labels = [];
            $counts = [];

            foreach ($statuses as $status => $label) {
                $count = (int) ($grouped[$status] ?? 0);

                if ($count > 0) {
                    $labels[] = $label;
                    $counts[] = $count;
                }
            }

            return [
                'labels' => $labels,
                'counts' => $counts,
            ];
        });
    }
}

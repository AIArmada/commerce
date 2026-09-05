<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

final class RevenueChartWidget extends ChartWidget
{
    use InteractsWithCashierChipData;

    protected ?string $heading = 'Revenue Trend (Last 12 Months)';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $data = $this->getRevenueData();

        return [
            'datasets' => [
                [
                    'label' => 'MRR',
                    'data' => array_values($data['mrr']),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => true,
                ],
                [
                    'label' => 'New Revenue',
                    'data' => array_values($data['new_revenue']),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgb(34, 197, 94)',
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
        $currency = $this->currency();

        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { return '{$currency} ' + value.toLocaleString(); }",
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{labels: array<string>, mrr: array<int>, new_revenue: array<int>}
     */
    private function getRevenueData(): array
    {
        $labels = [];
        $mrr = [];
        $newRevenue = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = CarbonImmutable::now()->subMonths($i);
            $labels[] = $date->format('M Y');

            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $monthMrr = $this->subscriptionQuery()
                ->where('chip_status', SubscriptionStatus::Active->value)
                ->where('created_at', '<=', $endOfMonth)
                ->where(function ($query) use ($startOfMonth): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $startOfMonth);
                })
                ->withSum('items', 'unit_amount')
                ->get()
                ->sum(function (Subscription $subscription): int {
                    return $this->normalizeToMonthly(
                        (int) ($subscription->items_sum_unit_amount ?? 0) * ($subscription->quantity ?? 1),
                        $subscription->billing_interval ?? 'month',
                        $subscription->billing_interval_count ?? 1
                    );
                });

            $mrr[] = (int) ($monthMrr / 100);

            $newSubscriptionsRevenue = $this->subscriptionQuery()
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->withSum('items', 'unit_amount')
                ->get()
                ->sum(function (Subscription $subscription): int {
                    return $this->normalizeToMonthly(
                        (int) ($subscription->items_sum_unit_amount ?? 0) * ($subscription->quantity ?? 1),
                        $subscription->billing_interval ?? 'month',
                        $subscription->billing_interval_count ?? 1
                    );
                });

            $newRevenue[] = (int) ($newSubscriptionsRevenue / 100);
        }

        return [
            'labels' => $labels,
            'mrr' => $mrr,
            'new_revenue' => $newRevenue,
        ];
    }
}

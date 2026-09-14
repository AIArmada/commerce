<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Override;

final class RevenueChartWidget extends ChartWidget
{
    use InteractsWithCashierChipData;

    protected ?string $heading = 'Revenue Trend (Last 12 Months)';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    #[Override]
    public static function canView(): bool
    {
        return static::hasWidgetOwnerContext();
    }

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
        $currency = $this->safeCurrencyCode();

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
        return $this->rememberWidgetValue('revenue.data', function (): array {
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
                    ->select($this->revenueColumns())
                    ->withSum('items', 'unit_amount')
                    ->get()
                    ->sum(fn (Subscription $subscription): int => $this->monthlyGrossAmount($subscription));

                $mrr[] = (int) ($monthMrr / 100);

                $newSubscriptionsRevenue = $this->subscriptionQuery()
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->select($this->revenueColumns())
                    ->withSum('items', 'unit_amount')
                    ->get()
                    ->sum(fn (Subscription $subscription): int => $this->monthlyGrossAmount($subscription));

                $newRevenue[] = (int) ($newSubscriptionsRevenue / 100);
            }

            return [
                'labels' => $labels,
                'mrr' => $mrr,
                'new_revenue' => $newRevenue,
            ];
        });
    }

    private function monthlyGrossAmount(Subscription $subscription): int
    {
        return $this->normalizeToMonthly(
            (int) ($subscription->items_sum_unit_amount ?? 0) * ($subscription->quantity ?? 1),
            $subscription->billing_interval ?? 'month',
            $subscription->billing_interval_count ?? 1,
        );
    }

    /**
     * @return list<string>
     */
    private function revenueColumns(): array
    {
        return [
            'id',
            'billing_interval',
            'billing_interval_count',
            'quantity',
            'created_at',
            'ends_at',
        ];
    }
}

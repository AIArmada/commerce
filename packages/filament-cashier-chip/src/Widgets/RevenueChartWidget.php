<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class RevenueChartWidget extends ChartWidget
{
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
        $currency = config('cashier-chip.currency', 'MYR');

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

        /** @var class-string<Subscription> $subscriptionModel */
        $subscriptionModel = Cashier::$subscriptionModel;

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->format('M Y');

            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            // Calculate MRR for the month
            $monthMrr = CashierChipOwnerScope::apply($subscriptionModel::query())
                ->where('chip_status', Subscription::STATUS_ACTIVE)
                ->where('created_at', '<=', $endOfMonth)
                ->where(function ($query) use ($startOfMonth): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $startOfMonth);
                })
                ->with('items')
                ->get()
                ->sum(function (Subscription $subscription): int {
                    return $this->normalizeToMonthly(
                        $subscription->items->sum('unit_amount') * ($subscription->quantity ?? 1),
                        $subscription->billing_interval ?? 'month',
                        $subscription->billing_interval_count ?? 1
                    );
                });

            $mrr[] = (int) ($monthMrr / 100);

            // Calculate new subscriptions revenue
            $newSubscriptionsRevenue = CashierChipOwnerScope::apply($subscriptionModel::query())
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->with('items')
                ->get()
                ->sum(function (Subscription $subscription): int {
                    return $this->normalizeToMonthly(
                        $subscription->items->sum('unit_amount') * ($subscription->quantity ?? 1),
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

    private function normalizeToMonthly(int $amount, string $interval, int $count): int
    {
        $multiplier = match ($interval) {
            'day' => 30 / $count,
            'week' => 4.33 / $count,
            'month' => 1 / $count,
            'year' => 1 / (12 * $count),
            default => 1,
        };

        return (int) round($amount * $multiplier);
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
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
            $firstMonth = CarbonImmutable::now()->subMonths(11)->startOfMonth();
            $lastMonth = CarbonImmutable::now()->endOfMonth();

            $mrrSubscriptions = $this->subscriptionQuery()
                ->where('chip_status', SubscriptionStatus::Active->value)
                ->where('created_at', '<=', $lastMonth)
                ->where(function ($query) use ($firstMonth): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $firstMonth);
                })
                ->select($this->revenueColumns())
                ->with('items:id,subscription_id,unit_amount,quantity')
                ->get();

            $newSubscriptions = $this->subscriptionQuery()
                ->whereBetween('created_at', [$firstMonth, $lastMonth])
                ->select($this->revenueColumns())
                ->with('items:id,subscription_id,unit_amount,quantity')
                ->get();

            for ($i = 11; $i >= 0; $i--) {
                $date = CarbonImmutable::now()->subMonths($i);
                $labels[] = $date->format('M Y');

                $startOfMonth = $date->copy()->startOfMonth();
                $endOfMonth = $date->copy()->endOfMonth();

                $monthMrr = $mrrSubscriptions
                    ->filter(fn (Subscription $subscription): bool => $subscription->created_at <= $endOfMonth
                        && ($subscription->ends_at === null || $subscription->ends_at >= $startOfMonth))
                    ->sum(fn (Subscription $subscription): int => $this->monthlyGrossAmount($subscription));

                $mrr[] = (int) ($monthMrr / 100);

                $newSubscriptionsRevenue = $newSubscriptions
                    ->filter(fn (Subscription $subscription): bool => $subscription->created_at >= $startOfMonth
                        && $subscription->created_at <= $endOfMonth)
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
            $subscription->items->sum(fn ($item): int => $item->totalAmount()),
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
            'chip_status',
            'created_at',
            'ends_at',
        ];
    }
}

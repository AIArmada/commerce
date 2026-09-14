<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class MRRWidget extends BaseWidget
{
    use InteractsWithCashierChipData;

    protected static ?int $sort = 1;

    #[Override]
    public static function canView(): bool
    {
        return static::hasWidgetOwnerContext();
    }

    protected function getStats(): array
    {
        $mrr = $this->calculateMRR();
        $previousMrr = $this->calculatePreviousMRR();
        $trend = $this->calculateTrend($mrr, $previousMrr);

        return [
            Stat::make('Monthly Recurring Revenue', $this->formatCurrency($mrr))
                ->description($trend['description'])
                ->descriptionIcon($trend['icon'])
                ->color($trend['color'])
                ->chart($this->getMRRChart()),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }

    private function calculateMRR(): int
    {
        return $this->rememberWidgetValue('mrr.current', fn (): int => $this->subscriptionQuery()
            ->whereActive()
            ->select($this->mrrColumns())
            ->withSum('items', 'unit_amount')
            ->get()
            ->sum(fn (Subscription $subscription): int => $this->monthlyNetAmount($subscription)));
    }

    private function calculatePreviousMRR(): int
    {
        return $this->rememberWidgetValue('mrr.previous', fn (): int => $this->subscriptionQuery()
            ->where('chip_status', SubscriptionStatus::Active->value)
            ->where('created_at', '<', CarbonImmutable::now()->subMonth())
            ->select($this->mrrColumns())
            ->withSum('items', 'unit_amount')
            ->get()
            ->sum(fn (Subscription $subscription): int => $this->monthlyNetAmount($subscription)));
    }

    private function monthlyNetAmount(Subscription $subscription): int
    {
        $interval = $subscription->billing_interval ?? 'month';
        $count = $subscription->billing_interval_count ?? 1;

        $monthlyAmount = $this->normalizeToMonthly(
            (int) ($subscription->items_sum_unit_amount ?? 0) * ($subscription->quantity ?? 1),
            $interval,
            $count,
        );

        if ($subscription->hasDiscount()) {
            $monthlyAmount -= $this->normalizeToMonthly(
                (int) ($subscription->coupon_discount ?? 0),
                $interval,
                $count,
            );
        }

        return max(0, $monthlyAmount);
    }

    /**
     * @return list<string>
     */
    private function mrrColumns(): array
    {
        return [
            'id',
            'billing_interval',
            'billing_interval_count',
            'quantity',
            'coupon_id',
            'coupon_discount',
            'created_at',
            'ends_at',
        ];
    }

    /**
     * @return array{description: string, icon: Heroicon, color: string}
     */
    private function calculateTrend(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'description' => 'No previous data',
                'icon' => Heroicon::Minus,
                'color' => 'gray',
            ];
        }

        $percentChange = (($current - $previous) / $previous) * 100;

        if ($percentChange > 0) {
            return [
                'description' => sprintf('+%.1f%% from last month', $percentChange),
                'icon' => Heroicon::ArrowTrendingUp,
                'color' => 'success',
            ];
        }

        if ($percentChange < 0) {
            return [
                'description' => sprintf('%.1f%% from last month', $percentChange),
                'icon' => Heroicon::ArrowTrendingDown,
                'color' => 'danger',
            ];
        }

        return [
            'description' => 'No change from last month',
            'icon' => Heroicon::Minus,
            'color' => 'gray',
        ];
    }

    /**
     * @return array<int>
     */
    private function getMRRChart(): array
    {
        return $this->rememberWidgetValue('mrr.chart', function (): array {
            $chart = [];

            for ($i = 5; $i >= 0; $i--) {
                $date = CarbonImmutable::now()->subMonths($i);
                $startOfMonth = $date->copy()->startOfMonth();
                $endOfMonth = $date->copy()->endOfMonth();

                $monthMrr = $this->subscriptionQuery()
                    ->where('chip_status', SubscriptionStatus::Active->value)
                    ->where('created_at', '<=', $endOfMonth)
                    ->where(function ($query) use ($startOfMonth): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $startOfMonth);
                    })
                    ->select($this->mrrColumns())
                    ->withSum('items', 'unit_amount')
                    ->get()
                    ->sum(fn (Subscription $subscription): int => $this->monthlyNetAmount($subscription));

                $chart[] = (int) ($monthMrr / 100);
            }

            return $chart;
        });
    }
}

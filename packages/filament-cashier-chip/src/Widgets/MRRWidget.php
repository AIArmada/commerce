<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class MRRWidget extends BaseWidget
{
    protected static ?int $sort = 1;

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
        $subscriptionModel = Cashier::$subscriptionModel;

        return $subscriptionModel::active()
            ->with('items')
            ->get()
            ->sum(function (Subscription $subscription): int {
                $monthlyAmount = $this->normalizeToMonthly(
                    $subscription->items->sum('unit_amount') * ($subscription->quantity ?? 1),
                    $subscription->billing_interval ?? 'month',
                    $subscription->billing_interval_count ?? 1
                );

                // Apply discount if any
                if ($subscription->hasDiscount()) {
                    $monthlyAmount -= ($subscription->coupon_discount ?? 0);
                }

                return max(0, $monthlyAmount);
            });
    }

    private function calculatePreviousMRR(): int
    {
        $subscriptionModel = Cashier::$subscriptionModel;

        return $subscriptionModel::where('chip_status', Subscription::STATUS_ACTIVE)
            ->where('created_at', '<', now()->subMonth())
            ->with('items')
            ->get()
            ->sum(function (Subscription $subscription): int {
                return $this->normalizeToMonthly(
                    $subscription->items->sum('unit_amount') * ($subscription->quantity ?? 1),
                    $subscription->billing_interval ?? 'month',
                    $subscription->billing_interval_count ?? 1
                );
            });
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
        $chart = [];
        $subscriptionModel = Cashier::$subscriptionModel;

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $monthMrr = $subscriptionModel::where('chip_status', Subscription::STATUS_ACTIVE)
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

            $chart[] = (int) ($monthMrr / 100);
        }

        return $chart;
    }

    private function formatCurrency(int $amount): string
    {
        $currency = config('filament-cashier-chip.currency', 'MYR');

        return mb_strtoupper($currency).' '.number_format($amount / 100, 2);
    }
}

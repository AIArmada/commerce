<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ChurnRateWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $churnRate = $this->calculateChurnRate();
        $previousChurnRate = $this->calculatePreviousChurnRate();

        return [
            Stat::make('Churn Rate', sprintf('%.1f%%', $churnRate))
                ->description($this->getChurnDescription($churnRate, $previousChurnRate))
                ->descriptionIcon($this->getChurnIcon($churnRate, $previousChurnRate))
                ->color($this->getChurnColor($churnRate))
                ->chart($this->getChurnChart()),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }

    private function calculateChurnRate(): float
    {
        $subscriptionModel = Cashier::$subscriptionModel;

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Subscribers at start of month
        $startCount = $subscriptionModel::where('created_at', '<', $startOfMonth)
            ->where(function ($query) use ($startOfMonth): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $startOfMonth);
            })
            ->whereIn('chip_status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->count();

        if ($startCount === 0) {
            return 0.0;
        }

        // Churned this month (subscriptions that ended)
        $churned = $subscriptionModel::whereNotNull('ends_at')
            ->whereBetween('ends_at', [$startOfMonth, $endOfMonth])
            ->count();

        return ($churned / $startCount) * 100;
    }

    private function calculatePreviousChurnRate(): float
    {
        $subscriptionModel = Cashier::$subscriptionModel;

        $startOfMonth = now()->subMonth()->startOfMonth();
        $endOfMonth = now()->subMonth()->endOfMonth();

        $startCount = $subscriptionModel::where('created_at', '<', $startOfMonth)
            ->where(function ($query) use ($startOfMonth): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $startOfMonth);
            })
            ->count();

        if ($startCount === 0) {
            return 0.0;
        }

        $churned = $subscriptionModel::whereNotNull('ends_at')
            ->whereBetween('ends_at', [$startOfMonth, $endOfMonth])
            ->count();

        return ($churned / $startCount) * 100;
    }

    private function getChurnDescription(float $current, float $previous): string
    {
        $diff = $current - $previous;

        if (abs($diff) < 0.1) {
            return 'Same as last month';
        }

        if ($diff > 0) {
            return sprintf('+%.1f%% from last month', $diff);
        }

        return sprintf('%.1f%% from last month', $diff);
    }

    private function getChurnIcon(float $current, float $previous): Heroicon
    {
        $diff = $current - $previous;

        if (abs($diff) < 0.1) {
            return Heroicon::Minus;
        }

        // For churn, lower is better, so inverted logic
        return $diff > 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown;
    }

    private function getChurnColor(float $rate): string
    {
        if ($rate <= 2) {
            return 'success';
        }

        if ($rate <= 5) {
            return 'warning';
        }

        return 'danger';
    }

    /**
     * @return array<float>
     */
    private function getChurnChart(): array
    {
        $chart = [];
        $subscriptionModel = Cashier::$subscriptionModel;

        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = now()->subMonths($i)->startOfMonth();
            $endOfMonth = now()->subMonths($i)->endOfMonth();

            $startCount = $subscriptionModel::where('created_at', '<', $startOfMonth)
                ->where(function ($query) use ($startOfMonth): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $startOfMonth);
                })
                ->count();

            if ($startCount === 0) {
                $chart[] = 0;

                continue;
            }

            $churned = $subscriptionModel::whereNotNull('ends_at')
                ->whereBetween('ends_at', [$startOfMonth, $endOfMonth])
                ->count();

            $chart[] = round(($churned / $startCount) * 100, 1);
        }

        return $chart;
    }
}

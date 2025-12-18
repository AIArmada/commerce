<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TrialConversionsWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected function getStats(): array
    {
        $conversionRate = $this->calculateConversionRate();
        $previousRate = $this->calculatePreviousConversionRate();
        $activeTrials = $this->getActiveTrialsCount();

        return [
            Stat::make('Trial Conversion Rate', sprintf('%.1f%%', $conversionRate))
                ->description($this->getTrendDescription($conversionRate, $previousRate))
                ->descriptionIcon($this->getTrendIcon($conversionRate, $previousRate))
                ->color($this->getConversionColor($conversionRate))
                ->chart($this->getConversionChart()),

            Stat::make('Active Trials', (string) $activeTrials)
                ->description('Currently trialing')
                ->descriptionIcon(Heroicon::Clock)
                ->color('warning'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }

    private function calculateConversionRate(): float
    {
        $subscriptionModel = Cashier::$subscriptionModel;

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Trials that ended this month
        $trialsEnded = $subscriptionModel::whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$startOfMonth, $endOfMonth])
            ->count();

        if ($trialsEnded === 0) {
            return 0.0;
        }

        // Trials that converted (still active after trial ended)
        $converted = $subscriptionModel::whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$startOfMonth, $endOfMonth])
            ->where('chip_status', Subscription::STATUS_ACTIVE)
            ->whereNull('ends_at')
            ->count();

        return ($converted / $trialsEnded) * 100;
    }

    private function calculatePreviousConversionRate(): float
    {
        $subscriptionModel = Cashier::$subscriptionModel;

        $startOfMonth = now()->subMonth()->startOfMonth();
        $endOfMonth = now()->subMonth()->endOfMonth();

        $trialsEnded = $subscriptionModel::whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$startOfMonth, $endOfMonth])
            ->count();

        if ($trialsEnded === 0) {
            return 0.0;
        }

        $converted = $subscriptionModel::whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$startOfMonth, $endOfMonth])
            ->where('chip_status', Subscription::STATUS_ACTIVE)
            ->whereNull('ends_at')
            ->count();

        return ($converted / $trialsEnded) * 100;
    }

    private function getActiveTrialsCount(): int
    {
        $subscriptionModel = Cashier::$subscriptionModel;

        return $subscriptionModel::query()->onTrial()->count();
    }

    private function getTrendDescription(float $current, float $previous): string
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

    private function getTrendIcon(float $current, float $previous): Heroicon
    {
        $diff = $current - $previous;

        if (abs($diff) < 0.1) {
            return Heroicon::Minus;
        }

        return $diff > 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown;
    }

    private function getConversionColor(float $rate): string
    {
        if ($rate >= 70) {
            return 'success';
        }

        if ($rate >= 40) {
            return 'warning';
        }

        return 'danger';
    }

    /**
     * @return array<float>
     */
    private function getConversionChart(): array
    {
        $chart = [];
        $subscriptionModel = Cashier::$subscriptionModel;

        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = now()->subMonths($i)->startOfMonth();
            $endOfMonth = now()->subMonths($i)->endOfMonth();

            $trialsEnded = $subscriptionModel::whereNotNull('trial_ends_at')
                ->whereBetween('trial_ends_at', [$startOfMonth, $endOfMonth])
                ->count();

            if ($trialsEnded === 0) {
                $chart[] = 0;

                continue;
            }

            $converted = $subscriptionModel::whereNotNull('trial_ends_at')
                ->whereBetween('trial_ends_at', [$startOfMonth, $endOfMonth])
                ->where('chip_status', Subscription::STATUS_ACTIVE)
                ->whereNull('ends_at')
                ->count();

            $chart[] = round(($converted / $trialsEnded) * 100, 1);
        }

        return $chart;
    }
}

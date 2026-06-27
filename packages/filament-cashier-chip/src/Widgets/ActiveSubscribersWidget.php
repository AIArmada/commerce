<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ActiveSubscribersWidget extends BaseWidget
{
    use InteractsWithCashierChipData;

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $activeCount = $this->getActiveSubscribersCount();
        $trialingCount = $this->getTrialingCount();
        $totalCount = $activeCount + $trialingCount;
        $previousCount = $this->getPreviousActiveCount();
        $trend = $this->calculateTrend($totalCount, $previousCount);

        return [
            Stat::make('Active Subscribers', (string) $totalCount)
                ->description($trend['description'])
                ->descriptionIcon($trend['icon'])
                ->color($trend['color'])
                ->chart($this->getSubscriberChart()),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }

    private function getActiveSubscribersCount(): int
    {
        return $this->subscriptionQuery()
            ->whereActive()
            ->count();
    }

    private function getTrialingCount(): int
    {
        return $this->subscriptionQuery()
            ->whereOnTrial()
            ->count();
    }

    private function getPreviousActiveCount(): int
    {
        return $this->subscriptionQuery()
            ->where('chip_status', SubscriptionStatus::Active->value)
            ->where('created_at', '<', now()->subMonth())
            ->count();
    }

    /**
     * @return array{description: string, icon: Heroicon, color: string}
     */
    private function calculateTrend(int $current, int $previous): array
    {
        $diff = $current - $previous;

        if ($diff > 0) {
            return [
                'description' => "+{$diff} from last month",
                'icon' => Heroicon::ArrowTrendingUp,
                'color' => 'success',
            ];
        }

        if ($diff < 0) {
            return [
                'description' => "{$diff} from last month",
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
    private function getSubscriberChart(): array
    {
        $chart = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $endOfMonth = $date->copy()->endOfMonth();

            $count = $this->subscriptionQuery()
                ->where('created_at', '<=', $endOfMonth)
                ->where(function ($query) use ($endOfMonth): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $endOfMonth);
                })
                ->whereIn('chip_status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
                ->count();

            $chart[] = $count;
        }

        return $chart;
    }
}

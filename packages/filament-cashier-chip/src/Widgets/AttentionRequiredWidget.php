<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AttentionRequiredWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected function getStats(): array
    {
        /** @var class-string<Subscription> $subscriptionModel */
        $subscriptionModel = Cashier::$subscriptionModel;

        $now = now();
        $inThreeDays = $now->copy()->addDays(3);

        $trialsEndingSoon = CashierChipOwnerScope::apply($subscriptionModel::query())
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>=', $now)
            ->where('trial_ends_at', '<=', $inThreeDays)
            ->where('chip_status', Subscription::STATUS_TRIALING)
            ->count();

        $pastDue = CashierChipOwnerScope::apply($subscriptionModel::query())
            ->where('chip_status', Subscription::STATUS_PAST_DUE)
            ->count();

        $gracePeriodEnding = CashierChipOwnerScope::apply($subscriptionModel::query())
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', $now)
            ->where('ends_at', '<=', $inThreeDays)
            ->count();

        $incomplete = CashierChipOwnerScope::apply($subscriptionModel::query())
            ->where('chip_status', Subscription::STATUS_INCOMPLETE)
            ->count();

        $unpaid = CashierChipOwnerScope::apply($subscriptionModel::query())
            ->where('chip_status', Subscription::STATUS_UNPAID)
            ->count();

        $totalAttention = $trialsEndingSoon + $pastDue + $gracePeriodEnding + $incomplete + $unpaid;

        return [
            Stat::make('Attention Required', $totalAttention)
                ->description($this->buildDescription($trialsEndingSoon, $pastDue, $gracePeriodEnding, $incomplete, $unpaid))
                ->descriptionIcon($totalAttention > 0 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
                ->color($this->getColor($totalAttention)),
        ];
    }

    protected function getColumns(): int
    {
        return 1;
    }

    private function buildDescription(int $trials, int $pastDue, int $grace, int $incomplete, int $unpaid): string
    {
        $parts = [];

        if ($trials > 0) {
            $parts[] = "{$trials} trials ending";
        }

        if ($pastDue > 0) {
            $parts[] = "{$pastDue} past due";
        }

        if ($grace > 0) {
            $parts[] = "{$grace} grace ending";
        }

        if ($incomplete > 0) {
            $parts[] = "{$incomplete} incomplete";
        }

        if ($unpaid > 0) {
            $parts[] = "{$unpaid} unpaid";
        }

        if (empty($parts)) {
            return 'All subscriptions healthy';
        }

        return implode(', ', array_slice($parts, 0, 3));
    }

    private function getColor(int $total): string
    {
        if ($total === 0) {
            return 'success';
        }

        if ($total <= 5) {
            return 'warning';
        }

        return 'danger';
    }
}

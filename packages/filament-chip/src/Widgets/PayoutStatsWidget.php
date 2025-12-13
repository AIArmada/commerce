<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\SendInstruction;
use DateTimeInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PayoutStatsWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $todayPayouts = $this->getTodayPayouts();
        $weekPayouts = $this->getWeekPayouts();
        $monthPayouts = $this->getMonthPayouts();
        $successRate = $this->getSuccessRate();

        return [
            Stat::make('Today\'s Payouts', $this->formatCurrency($todayPayouts))
                ->description('Sent today')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),

            Stat::make('This Week', $this->formatCurrency($weekPayouts))
                ->description('Last 7 days')
                ->descriptionIcon(Heroicon::CalendarDays)
                ->color('primary'),

            Stat::make('This Month', $this->formatCurrency($monthPayouts))
                ->description('Current month')
                ->descriptionIcon(Heroicon::Calendar)
                ->color('info'),

            Stat::make('Success Rate', sprintf('%s%%', $successRate))
                ->description('Completed vs failed')
                ->descriptionIcon(Heroicon::ChartBar)
                ->color($successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger')),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function getTodayPayouts(): float
    {
        return $this->getPayoutsForPeriod(now()->startOfDay());
    }

    private function getWeekPayouts(): float
    {
        return $this->getPayoutsForPeriod(now()->subDays(7));
    }

    private function getMonthPayouts(): float
    {
        return $this->getPayoutsForPeriod(now()->startOfMonth());
    }

    private function getPayoutsForPeriod(DateTimeInterface $since): float
    {
        return SendInstruction::query()
            ->whereIn('state', ['completed', 'processed'])
            ->where('created_at', '>=', $since)
            ->get()
            ->sum(fn (SendInstruction $instruction): float => (float) $instruction->amount);
    }

    private function getSuccessRate(): float
    {
        $successStates = ['completed', 'processed'];
        $failedStates = ['failed', 'cancelled', 'rejected'];

        $successful = SendInstruction::whereIn('state', $successStates)->count();
        $failed = SendInstruction::whereIn('state', $failedStates)->count();

        $total = $successful + $failed;

        if ($total === 0) {
            return 100.0;
        }

        return round(($successful / $total) * 100, 1);
    }

    private function formatCurrency(float $amount): string
    {
        $currency = config('filament-chip.default_currency', 'MYR');

        return sprintf('%s %s', mb_strtoupper($currency), number_format($amount, 2));
    }
}

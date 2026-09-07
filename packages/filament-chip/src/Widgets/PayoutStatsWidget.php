<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\SendInstruction;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
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

    private function getTodayPayouts(): int
    {
        return $this->getPayoutsForPeriod(CarbonImmutable::now()->startOfDay());
    }

    private function getWeekPayouts(): int
    {
        return $this->getPayoutsForPeriod(CarbonImmutable::now()->subDays(7));
    }

    private function getMonthPayouts(): int
    {
        return $this->getPayoutsForPeriod(CarbonImmutable::now()->startOfMonth());
    }

    private function getPayoutsForPeriod(DateTimeInterface $since): int
    {
        $totalMajor = (string) SendInstruction::query()
            ->forOwner()
            ->where('state', 'completed')
            ->where('created_at', '>=', $since)
            ->sum('amount');

        // CHIP stores Send amounts in major units; convert the aggregate once
        // at the integration boundary with explicit half-up rounding.
        $scale = 10 ** MoneyFormatter::precisionFor((string) config('filament-chip.default_currency', 'MYR'));

        return (int) round((float) $totalMajor * $scale, 0, PHP_ROUND_HALF_UP);
    }

    private function getSuccessRate(): float
    {
        $successful = SendInstruction::query()->forOwner()->where('state', 'completed')->count();
        $failed = SendInstruction::query()->forOwner()->whereIn('state', ['rejected', 'deleted'])->count();

        $total = $successful + $failed;

        if ($total === 0) {
            return 100.0;
        }

        return round(($successful / $total) * 100, 1);
    }

    private function formatCurrency(int $amountInMinorUnits): string
    {
        return MoneyFormatter::formatMinor($amountInMinorUnits, (string) config('filament-chip.default_currency', 'MYR'));
    }
}

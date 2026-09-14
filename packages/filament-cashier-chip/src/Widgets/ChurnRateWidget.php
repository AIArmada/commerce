<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class ChurnRateWidget extends BaseWidget
{
    use InteractsWithCashierChipData;

    protected static ?int $sort = 3;

    #[Override]
    public static function canView(): bool
    {
        return static::hasWidgetOwnerContext();
    }

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
        return $this->rememberWidgetValue('churn.current', function (): float {
            $startOfMonth = CarbonImmutable::now()->startOfMonth();
            $endOfMonth = CarbonImmutable::now()->endOfMonth();

            return $this->churnForMonth($startOfMonth, $endOfMonth);
        });
    }

    private function calculatePreviousChurnRate(): float
    {
        return $this->rememberWidgetValue('churn.previous', function (): float {
            $startOfMonth = CarbonImmutable::now()->subMonth()->startOfMonth();
            $endOfMonth = CarbonImmutable::now()->subMonth()->endOfMonth();

            return $this->churnForMonth($startOfMonth, $endOfMonth);
        });
    }

    private function churnForMonth(CarbonImmutable $startOfMonth, CarbonImmutable $endOfMonth): float
    {
        $startCount = $this->subscriptionQuery()
            ->where('created_at', '<', $startOfMonth)
            ->where(function ($query) use ($startOfMonth): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $startOfMonth);
            })
            ->whereIn('chip_status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->count();

        if ($startCount === 0) {
            return 0.0;
        }

        $churned = $this->subscriptionQuery()
            ->whereNotNull('ends_at')
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
        return $this->rememberWidgetValue('churn.chart', function (): array {
            $chart = [];

            for ($i = 5; $i >= 0; $i--) {
                $startOfMonth = CarbonImmutable::now()->subMonths($i)->startOfMonth();
                $endOfMonth = CarbonImmutable::now()->subMonths($i)->endOfMonth();

                $chart[] = round($this->churnForMonth($startOfMonth, $endOfMonth), 1);
            }

            return $chart;
        });
    }
}

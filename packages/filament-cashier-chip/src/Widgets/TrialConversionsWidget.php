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

final class TrialConversionsWidget extends BaseWidget
{
    use InteractsWithCashierChipData;

    protected static ?int $sort = 6;

    #[Override]
    public static function canView(): bool
    {
        return static::hasWidgetOwnerContext();
    }

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
        return $this->rememberWidgetValue('trials.conversion', function (): float {
            $startOfMonth = CarbonImmutable::now()->startOfMonth();
            $endOfMonth = CarbonImmutable::now()->endOfMonth();

            return $this->conversionForMonth($startOfMonth, $endOfMonth);
        });
    }

    private function calculatePreviousConversionRate(): float
    {
        return $this->rememberWidgetValue('trials.previous', function (): float {
            $startOfMonth = CarbonImmutable::now()->subMonth()->startOfMonth();
            $endOfMonth = CarbonImmutable::now()->subMonth()->endOfMonth();

            return $this->conversionForMonth($startOfMonth, $endOfMonth);
        });
    }

    private function conversionForMonth(CarbonImmutable $startOfMonth, CarbonImmutable $endOfMonth): float
    {
        $row = $this->subscriptionQuery()
            ->selectRaw('COUNT(*) AS trials_ended, COUNT(CASE WHEN chip_status = ? AND ends_at IS NULL THEN 1 END) AS converted', [SubscriptionStatus::Active->value])
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$startOfMonth, $endOfMonth])
            ->first();

        $trialsEnded = (int) ($row?->getAttribute('trials_ended') ?? 0);

        if ($trialsEnded === 0) {
            return 0.0;
        }

        $converted = (int) ($row?->getAttribute('converted') ?? 0);

        return ($converted / $trialsEnded) * 100;
    }

    private function getActiveTrialsCount(): int
    {
        return $this->rememberWidgetValue(
            'trials.active',
            fn (): int => $this->subscriptionQuery()->whereOnTrial()->count(),
        );
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
        return $this->rememberWidgetValue('trials.chart', function (): array {
            $chart = [];

            for ($i = 5; $i >= 0; $i--) {
                $startOfMonth = CarbonImmutable::now()->subMonths($i)->startOfMonth();
                $endOfMonth = CarbonImmutable::now()->subMonths($i)->endOfMonth();

                $chart[] = round($this->conversionForMonth($startOfMonth, $endOfMonth), 1);
            }

            return $chart;
        });
    }
}

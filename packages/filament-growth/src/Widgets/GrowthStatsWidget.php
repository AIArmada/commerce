<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Widgets;

use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class GrowthStatsWidget extends StatsOverviewWidget
{
    use FormatsMoney {
        formatMoney as private formatMinorMoney;
    }

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $experiments = Experiment::query()->forOwner()->with('trackedProperty')->get();
        $summary = $experiments->reduce(function (array $carry, Experiment $experiment): array {
            $metrics = app(AggregateExperimentMetrics::class)->handle($experiment);
            $currency = (string) ($metrics['currency'] ?? config('signals.defaults.currency', 'MYR'));

            $carry['revenue_minor_by_currency'][$currency] = ($carry['revenue_minor_by_currency'][$currency] ?? 0) + (int) $metrics['totals']['revenue_minor'];
            $carry['winner_ready'] += (int) (($metrics['totals']['assignments'] > 0) ? 1 : 0);

            return $carry;
        }, [
            'revenue_minor_by_currency' => [],
            'winner_ready' => 0,
        ]);

        $revenueSummary = $this->formatRevenueSummary($summary['revenue_minor_by_currency']);
        $winnersDescription = number_format((int) $summary['winner_ready']) . ' experiment(s) with measurable results';

        if ($revenueSummary['details'] !== null) {
            $winnersDescription .= ' • ' . $revenueSummary['details'];
        }

        $activeExperiments = $experiments->where('status', ExperimentStatus::Active)->count();
        $variantCount = Variant::query()->forOwner()->count();
        $assignmentCount = Assignment::query()->forOwner()->count();

        return [
            Stat::make('Active Experiments', number_format($activeExperiments))
                ->description('Currently splitting traffic')
                ->color('success'),
            Stat::make('Variants', number_format($variantCount))
                ->description('Configured across all experiments')
                ->color('primary'),
            Stat::make('Assignments', number_format($assignmentCount))
                ->description('Sticky subject allocations recorded')
                ->color('info'),
            Stat::make('Tracked Revenue', $revenueSummary['value'])
                ->description($winnersDescription)
                ->color('warning'),
        ];
    }

    private function formatDisplayMoney(int $minor, string $currency): string
    {
        return $this->formatMinorMoney($minor, $currency);
    }

    /**
     * @param  array<string, int>  $revenueByCurrency
     * @return array{value: string, details: string|null}
     */
    private function formatRevenueSummary(array $revenueByCurrency): array
    {
        if ($revenueByCurrency === []) {
            $currency = (string) config('signals.defaults.currency', 'MYR');

            return [
                'value' => $this->formatDisplayMoney(0, $currency),
                'details' => null,
            ];
        }

        if (count($revenueByCurrency) === 1) {
            $currency = array_key_first($revenueByCurrency);

            return [
                'value' => $this->formatDisplayMoney((int) $revenueByCurrency[$currency], (string) $currency),
                'details' => null,
            ];
        }

        $details = collect($revenueByCurrency)
            ->map(fn (int $minor, string $currency): string => $this->formatDisplayMoney($minor, $currency))
            ->implode(' / ');

        return [
            'value' => 'Mixed',
            'details' => $details,
        ];
    }
}

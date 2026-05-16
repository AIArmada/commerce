<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Pages;

use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Enums\ExperimentModuleType;
use AIArmada\Growth\Models\Experiment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

final class ExperimentResultsPage extends Page implements HasForms
{
    use FormatsMoney {
        formatMoney as private formatMinorMoney;
    }
    use InteractsWithForms;

    public ?string $experimentId = null;

    public ?string $chartMetric = 'revenue_per_visitor';

    /** @var array<string, mixed> */
    public array $results = [];

    /** @var array<int, array<string, mixed>> */
    public array $variantComparison = [];

    /** @var array<string, mixed>|null */
    public ?array $winnerSummary = null;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Results';

    protected static ?string $title = 'Experiment Results';

    protected static ?string $slug = 'growth/results';

    /** @var view-string */
    protected string $view = 'filament-growth::pages.experiment-results';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation_group', 'Growth');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-growth.resources.navigation_sort.results', 11);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-growth.features.results', true);
    }

    public function mount(): void
    {
        $this->experimentId = request()->query('experiment', $this->defaultExperimentId());

        $this->form->fill([
            'experimentId' => $this->experimentId,
            'chartMetric' => $this->chartMetric,
        ]);

        $this->loadResults();
    }

    public function getTitle(): string | Htmlable
    {
        return 'Experiment Results';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('experimentId')
                        ->label('Experiment')
                        ->options($this->experimentOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (): null => $this->loadResults()),

                    Forms\Components\Select::make('chartMetric')
                        ->label('Chart Metric')
                        ->options($this->metricOptions())
                        ->default('revenue_per_visitor')
                        ->live()
                        ->afterStateUpdated(fn (): null => $this->loadResults()),
                ]),
        ]);
    }

    public function loadResults(): void
    {
        $experiment = $this->selectedExperiment();

        if (! $experiment instanceof Experiment) {
            $this->results = [];
            $this->variantComparison = [];
            $this->winnerSummary = null;

            return;
        }

        $results = app(AggregateExperimentMetrics::class)->handle($experiment);

        $this->results = $results;
        $this->variantComparison = $this->buildVariantComparison($results['variants'], $this->chartMetric ?? 'revenue_per_visitor');
        $this->winnerSummary = $this->buildWinnerSummary($results);
    }

    public function selectedExperiment(): ?Experiment
    {
        if (! is_string($this->experimentId) || $this->experimentId === '') {
            return null;
        }

        $experiment = Experiment::query()
            ->forOwner()
            ->with(['trackedProperty'])
            ->whereKey($this->experimentId)
            ->first();

        return $experiment instanceof Experiment ? $experiment : null;
    }

    public function formatMoney(int $minor): string
    {
        return $this->formatMinorMoney($minor, $this->currentCurrency());
    }

    public function formatMetricValue(string $metric, array $variant): string
    {
        $value = $this->numericMetricValue($metric, $variant);

        return match ($metric) {
            'conversion_rate' => number_format($value * 100, 2) . '%',
            'revenue_minor', 'revenue_per_visitor' => $this->formatMoney((int) round($value)),
            default => number_format((int) round($value)),
        };
    }

    public function metricLabel(string $metric): string
    {
        return $this->metricOptions()[$metric] ?? ucfirst(str_replace('_', ' ', $metric));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageExperiments')
                ->label('Manage Experiments')
                ->icon('heroicon-o-beaker')
                ->url(fn (): string => ExperimentResource::getUrl('index')),
            Action::make('refreshResults')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn (): null => $this->loadResults()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'experiment' => $this->selectedExperiment(),
            'winnerSummary' => $this->winnerSummary,
            'variantComparison' => $this->variantComparison,
            'chartMetricLabel' => $this->metricLabel($this->chartMetric ?? 'revenue_per_visitor'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function experimentOptions(): array
    {
        return Experiment::query()
            ->forOwner()
            ->orderByDesc('created_at')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Experiment $experiment): array => [(string) $experiment->getKey() => (string) $experiment->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function metricOptions(): array
    {
        return [
            'revenue_per_visitor' => 'Revenue Per Visitor',
            'revenue_minor' => 'Revenue',
            'conversion_rate' => 'Conversion Rate',
            'checkout_starts' => 'Checkout Starts',
            'purchases' => 'Purchases',
        ];
    }

    private function defaultExperimentId(): ?string
    {
        $experimentId = Experiment::query()
            ->forOwner()
            ->orderByDesc('created_at')
            ->value('id');

        return is_scalar($experimentId) ? (string) $experimentId : null;
    }

    /**
     * @param  array<int, array<string, float|int|string|null>>  $variants
     * @return array<int, array<string, mixed>>
     */
    private function buildVariantComparison(array $variants, string $metric): array
    {
        $maxValue = collect($variants)
            ->map(fn (array $variant): float => $this->numericMetricValue($metric, $variant))
            ->max() ?? 0;

        $colors = [
            'bg-primary-500',
            'bg-success-500',
            'bg-warning-500',
            'bg-info-500',
            'bg-danger-500',
        ];

        return collect($variants)
            ->values()
            ->map(function (array $variant, int $index) use ($colors, $maxValue, $metric): array {
                $value = $this->numericMetricValue($metric, $variant);

                return [
                    'variant_id' => (string) ($variant['variant_id'] ?? ''),
                    'code' => (string) ($variant['code'] ?? ''),
                    'name' => (string) ($variant['name'] ?? 'Variant'),
                    'value_label' => $this->formatMetricValue($metric, $variant),
                    'percent' => ($maxValue > 0 && $value > 0) ? max(4, round(($value / $maxValue) * 100, 2)) : 0,
                    'color_class' => $colors[$index % count($colors)],
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $results
     * @return array<string, mixed>|null
     */
    private function buildWinnerSummary(array $results): ?array
    {
        $winnerVariantId = $results['winner_variant_id'] ?? null;

        if (! is_string($winnerVariantId) || $winnerVariantId === '') {
            return null;
        }

        $winner = collect($results['variants'] ?? [])->firstWhere('variant_id', $winnerVariantId);

        if (! is_array($winner)) {
            return null;
        }

        return [
            'variant_id' => $winnerVariantId,
            'code' => (string) ($winner['code'] ?? ''),
            'name' => (string) ($winner['name'] ?? 'Winner'),
            'winner_metric_label' => $this->metricLabel((string) ($results['winner_metric'] ?? 'revenue_per_visitor')),
            'winner_metric_value' => $this->formatMetricValue((string) ($results['winner_metric'] ?? 'revenue_per_visitor'), $winner),
            'revenue' => $this->formatMoney((int) ($winner['revenue_minor'] ?? 0)),
            'conversion_rate' => $this->formatMetricValue('conversion_rate', $winner),
            'purchases' => number_format((int) ($winner['purchases'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function numericMetricValue(string $metric, array $variant): float
    {
        $value = $variant[$metric] ?? 0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function moduleLabel(): ?string
    {
        $experiment = $this->selectedExperiment();

        if (! $experiment instanceof Experiment) {
            return null;
        }

        return ExperimentModuleType::labelFor($experiment->module_type);
    }

    private function currentCurrency(): string
    {
        return (string) ($this->results['currency'] ?? $this->selectedExperiment()?->trackedProperty?->currency ?? config('signals.defaults.currency', 'MYR'));
    }
}

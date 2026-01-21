<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Services\AffiliateReportService;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

final class ReportsPage extends Page implements HasForms
{
    use InteractsWithForms;

    public ?string $period = 'month';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public array $reportData = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | UnitEnum | null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 10;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.reports';

    public function mount(): void
    {
        $this->form->fill([
            'period' => 'month',
        ]);

        $this->generateReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(4)
                ->schema([
                    Forms\Components\Select::make('period')
                        ->options([
                            'week' => 'Last 7 Days',
                            'month' => 'Last 30 Days',
                            'quarter' => 'Last 90 Days',
                            'year' => 'Last Year',
                            'custom' => 'Custom Range',
                        ])
                        ->default('month')
                        ->live()
                        ->afterStateUpdated(fn () => $this->generateReport()),

                    Forms\Components\DatePicker::make('startDate')
                        ->label('Start Date')
                        ->visible(fn (Get $get) => $get('period') === 'custom')
                        ->afterStateUpdated(fn () => $this->generateReport()),

                    Forms\Components\DatePicker::make('endDate')
                        ->label('End Date')
                        ->visible(fn (Get $get) => $get('period') === 'custom')
                        ->afterStateUpdated(fn () => $this->generateReport()),
                ]),
        ]);
    }

    public function generateReport(): void
    {
        $service = app(AffiliateReportService::class);

        $startDate = match ($this->period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            'custom' => $this->startDate ? now()->parse($this->startDate) : now()->subMonth(),
            default => now()->subMonth(),
        };

        $endDate = $this->period === 'custom' && $this->endDate
            ? now()->parse($this->endDate)
            : now();

        $this->reportData = [
            'summary' => $service->getSummary($startDate, $endDate),
            'top_affiliates' => $service->getTopAffiliates($startDate, $endDate, 10),
            'conversion_trend' => $service->getConversionTrend($startDate, $endDate),
            'traffic_sources' => $service->getTrafficSources($startDate, $endDate),
        ];
    }

    public function getViewData(): array
    {
        return [
            'reportData' => $this->reportData,
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSavedSignalReportState;
use AIArmada\Signals\Services\ConversionFunnelReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

final class ConversionFunnelReport extends ReportPage
{
    use InteractsWithSavedSignalReportState;

    #[Url]
    public string $savedReportId = '';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?string $navigationLabel = 'Conversion Funnel';

    protected static ?string $title = 'Conversion Funnel';

    protected static ?string $slug = 'signals/conversion-funnel';

    protected string $view = 'filament-signals::pages.conversion-funnel-report';

    /**
     * @var array{summary: array{started:int,completed:int,paid:int,started_label:string,completed_label:string,paid_label:string,start_to_complete_rate:float,complete_to_paid_rate:float,overall_rate:float,start_drop_off:int,complete_drop_off:int,revenue_minor?:int,currency?:string,revenue?:array<string, int>}, stages: list<array{label:string,event_name:string,count:int,rate_from_previous:float,rate_from_start:float,drop_off:int,revenue_minor?:int,currency?:string,revenue?:array<string, int>}>}|null
     */
    private ?array $reportCache = null;

    /** @var list<string|null> */
    private array $reportCacheParameters = [];

    public function mount(): void
    {
        $this->initializeDefaultDateRange();
        $this->sanitizeSavedReportState();
    }

    /**
     * @return array{summary: array{started:int,completed:int,paid:int,started_label:string,completed_label:string,paid_label:string,start_to_complete_rate:float,complete_to_paid_rate:float,overall_rate:float,start_drop_off:int,complete_drop_off:int,revenue_minor?:int,currency?:string,revenue?:array<string, int>}, stages: list<array{label:string,event_name:string,count:int,rate_from_previous:float,rate_from_start:float,drop_off:int,revenue_minor?:int,currency?:string,revenue?:array<string, int>}>}
     */
    private function report(): array
    {
        $parameters = [
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
            $this->savedReportId !== '' ? $this->savedReportId : null,
        ];

        if ($this->reportCache === null || $this->reportCacheParameters !== $parameters) {
            $this->reportCache = app(ConversionFunnelReportService::class)->report(...$parameters);
            $this->reportCacheParameters = $parameters;
        }

        return $this->reportCache;
    }

    protected function savedReportType(): string
    {
        return 'conversion_funnel';
    }

    public static function getNavigationSort(): int
    {
        return (int) config('filament-signals.resources.navigation_sort.conversion_funnel', 16);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.conversion_funnel', true)
            && static::canAccess();
    }

    /**
     * @return array{started:int,completed:int,paid:int,started_label:string,completed_label:string,paid_label:string,start_to_complete_rate:float,complete_to_paid_rate:float,overall_rate:float,start_drop_off:int,complete_drop_off:int,revenue_minor?:int,currency?:string,revenue?:array<string, int>}
     */
    public function getSummary(): array
    {
        return $this->report()['summary'];
    }

    /**
     * @return list<array{label:string,event_name:string,count:int,rate_from_previous:float,rate_from_start:float,drop_off:int,revenue_minor?:int,currency?:string,revenue?:array<string, int>}>
     */
    public function getStages(): array
    {
        return $this->report()['stages'];
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getDateRangeHeaderActions(
                app(ConversionFunnelReportService::class)->getTrackedPropertyOptions(),
                app(SignalSegmentReportFilter::class)->getSegmentOptions(),
            ),
            Action::make('funnelDefinition')
                ->label('Funnel')
                ->icon(Heroicon::OutlinedBookmarkSquare)
                ->form([
                    Select::make('saved_report_id')
                        ->label('Saved Funnel')
                        ->options(app(ConversionFunnelReportService::class)->getSavedReportOptions())
                        ->default($this->savedReportId !== '' ? $this->savedReportId : null)
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $this->savedReportId = is_string($data['saved_report_id'] ?? null)
                        ? $data['saved_report_id']
                        : '';

                    $this->sanitizeSavedReportState();
                }),
        ];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSavedSignalReportState;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use AIArmada\Signals\Services\ConversionFunnelReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

final class ConversionFunnelReport extends Page
{
    use FormatsSignalsReportValues;
    use InteractsWithSavedSignalReportState;
    use InteractsWithSignalsDateRange;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $trackedPropertyId = '';

    #[Url]
    public string $signalSegmentId = '';

    #[Url]
    public string $savedReportId = '';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static ?string $navigationLabel = 'Conversion Funnel';

    protected static ?string $title = 'Conversion Funnel';

    protected static ?string $slug = 'signals/conversion-funnel';

    protected string $view = 'filament-signals::pages.conversion-funnel-report';

    public function mount(): void
    {
        $this->initializeDefaultDateRange();
        $this->sanitizeSavedReportState();
    }

    protected function savedReportType(): string
    {
        return 'conversion_funnel';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.conversion_funnel', 16);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.conversion_funnel', true);
    }

    /**
     * @return array{started:int,completed:int,paid:int,started_label:string,completed_label:string,paid_label:string,start_to_complete_rate:float,complete_to_paid_rate:float,overall_rate:float,start_drop_off:int,complete_drop_off:int,revenue_minor:int}
     */
    public function getSummary(): array
    {
        return app(ConversionFunnelReportService::class)->summary(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
            $this->savedReportId !== '' ? $this->savedReportId : null,
        );
    }

    /**
     * @return list<array{label:string,event_name:string,count:int,rate_from_previous:float,rate_from_start:float,drop_off:int,revenue_minor:int}>
     */
    public function getStages(): array
    {
        return app(ConversionFunnelReportService::class)->stages(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
            $this->savedReportId !== '' ? $this->savedReportId : null,
        );
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

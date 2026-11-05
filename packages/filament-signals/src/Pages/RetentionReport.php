<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSavedSignalReportState;
use AIArmada\Signals\Services\RetentionReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

final class RetentionReport extends ReportPage
{
    use InteractsWithSavedSignalReportState;

    #[Url]
    public string $savedReportId = '';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Retention';

    protected static ?string $title = 'Retention';

    protected static ?string $slug = 'signals/retention';

    protected string $view = 'filament-signals::pages.retention-report';

    /**
     * @var array{summary: array{cohorts:int,identities:int,windows:list<array{days:int,retained:int,avg_retention_rate:float}>}, rows: list<array{cohort_date:string,cohort_size:int,windows:list<array{days:int,retained:int,retention_rate:float}>}>}|null
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
     * @return array{summary: array{cohorts:int,identities:int,windows:list<array{days:int,retained:int,avg_retention_rate:float}>}, rows: list<array{cohort_date:string,cohort_size:int,windows:list<array{days:int,retained:int,retention_rate:float}>}>}
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
            $this->reportCache = app(RetentionReportService::class)->report(...$parameters);
            $this->reportCacheParameters = $parameters;
        }

        return $this->reportCache;
    }

    protected function savedReportType(): string
    {
        return 'retention';
    }

    public static function getNavigationSort(): int
    {
        return (int) config('filament-signals.resources.navigation_sort.retention', 19);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.retention', true)
            && static::canAccess();
    }

    /**
     * @return array{cohorts:int,identities:int,windows:list<array{days:int,retained:int,avg_retention_rate:float}>}
     */
    public function getSummary(): array
    {
        return $this->report()['summary'];
    }

    /**
     * @return list<array{cohort_date:string,cohort_size:int,windows:list<array{days:int,retained:int,retention_rate:float}>}>
     */
    public function getRows(): array
    {
        return $this->report()['rows'];
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getDateRangeHeaderActions(
                app(RetentionReportService::class)->getTrackedPropertyOptions(),
                app(SignalSegmentReportFilter::class)->getSegmentOptions(),
            ),
            Action::make('savedRetention')
                ->label('Saved Retention')
                ->schema([
                    Select::make('savedReportId')
                        ->label('Saved Report')
                        ->options(app(RetentionReportService::class)->getSavedReportOptions())
                        ->searchable()
                        ->preload(),
                ])
                ->fillForm([
                    'savedReportId' => $this->savedReportId,
                ])
                ->action(function (array $data): void {
                    $this->savedReportId = (string) ($data['savedReportId'] ?? '');
                    $this->sanitizeSavedReportState();
                }),
        ];
    }
}

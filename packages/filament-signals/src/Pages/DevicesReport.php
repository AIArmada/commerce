<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use AIArmada\Signals\Services\DevicesReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

final class DevicesReport extends Page implements HasTable
{
    use FormatsSignalsReportValues;
    use InteractsWithSignalsDateRange;
    use InteractsWithTable;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $trackedPropertyId = '';

    #[Url]
    public string $signalSegmentId = '';

    /** @var 'device_type'|'browser'|'os'|'brand_model' */
    #[Url]
    public string $activeTab = 'device_type';

    #[Url]
    public bool $includeBots = false;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $navigationLabel = 'Devices & Technology';

    protected static ?string $title = 'Devices & Technology';

    protected static ?string $slug = 'signals/devices';

    protected string $view = 'filament-signals::pages.devices-report';

    public function mount(): void
    {
        $this->initializeDefaultDateRange();
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.devices_report', 23);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.devices_report', true);
    }

    /**
     * @return array{sessions:int,browsers:int,operating_systems:int,brands:int,bots:int}
     */
    public function getSummary(): array
    {
        return app(DevicesReportService::class)->summary(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
        );
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function toggleBots(): void
    {
        $this->includeBots = ! $this->includeBots;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $service = app(DevicesReportService::class);
        $propertyId = $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null;
        $excludeBots = ! $this->includeBots;

        $query = match ($this->activeTab) {
            'browser' => $service->getBrowserQuery($propertyId, $this->dateFrom, $this->dateTo, $this->signalSegmentId !== '' ? $this->signalSegmentId : null, $excludeBots),
            'os' => $service->getOsQuery($propertyId, $this->dateFrom, $this->dateTo, $this->signalSegmentId !== '' ? $this->signalSegmentId : null, $excludeBots),
            'brand_model' => $service->getBrandModelQuery($propertyId, $this->dateFrom, $this->dateTo, $this->signalSegmentId !== '' ? $this->signalSegmentId : null, $excludeBots),
            default => $service->getDeviceTypeQuery($propertyId, $this->dateFrom, $this->dateTo, $this->signalSegmentId !== '' ? $this->signalSegmentId : null, $excludeBots),
        };

        $columns = match ($this->activeTab) {
            'browser' => [
                TextColumn::make('browser')
                    ->label('Browser')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('sessions')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('visitors')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
            ],
            'os' => [
                TextColumn::make('os')
                    ->label('Operating System')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('sessions')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('visitors')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
            ],
            'brand_model' => [
                TextColumn::make('device_brand')
                    ->label('Brand')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('sessions')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('visitors')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
            ],
            default => [
                TextColumn::make('device_type')
                    ->label('Device Type')
                    ->weight('medium')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) ($state ?? 'unknown'))),
                TextColumn::make('sessions')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('visitors')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
            ],
        };

        return $table
            ->query($query)
            ->defaultSort('sessions', 'desc')
            ->defaultKeySort(false)
            ->columns($columns)
            ->emptyStateHeading('No device data recorded yet')
            ->emptyStateDescription('Device information will appear here once Signals starts capturing traffic with User-Agent parsing enabled.');
    }

    protected function getHeaderActions(): array
    {
        return $this->getDateRangeHeaderActions(
            app(DevicesReportService::class)->getTrackedPropertyOptions(),
            app(SignalSegmentReportFilter::class)->getSegmentOptions(),
        );
    }
}

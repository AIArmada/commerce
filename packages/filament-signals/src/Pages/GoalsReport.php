<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use AIArmada\Signals\Services\GoalsReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

final class GoalsReport extends Page
{
    use FormatsSignalsReportValues;
    use InteractsWithSignalsDateRange;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $trackedPropertyId = '';

    #[Url]
    public string $signalSegmentId = '';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Goals';

    protected static ?string $title = 'Goals';

    protected static ?string $slug = 'signals/goals';

    protected string $view = 'filament-signals::pages.goals-report';

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
        return (int) config('filament-signals.resources.navigation_sort.goals_report', 22);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.goals_report', true);
    }

    /**
     * @return array{goals:int,goal_hits:int,visitors:int,revenue_minor:int,avg_goal_rate:float}
     */
    public function getSummary(): array
    {
        return app(GoalsReportService::class)->summary(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
        );
    }

    /**
     * @return list<array{id:string,name:string,goal_type:string,event_name:string,event_category:?string,tracked_property_name:?string,goal_hits:int,visitors:int,revenue_minor:int,goal_rate:float,last_hit_at:?string}>
     */
    public function getRows(): array
    {
        return app(GoalsReportService::class)->rows(
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
        );
    }

    protected function getHeaderActions(): array
    {
        return $this->getDateRangeHeaderActions(
            app(GoalsReportService::class)->getTrackedPropertyOptions(),
            app(SignalSegmentReportFilter::class)->getSegmentOptions(),
        );
    }
}

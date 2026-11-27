<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages;

use AIArmada\Signals\Features\Ingestion\Api\TelemetryRead;
use AIArmada\Signals\Features\Ingestion\Extensions\GoalsReportService;
use AIArmada\Signals\Features\Reporting\Api\SegmentOptions;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class GoalsReport extends ReportPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Goals';

    protected static ?string $title = 'Goals';

    protected static ?string $slug = 'signals/goals';

    protected string $view = 'filament-signals::pages.goals-report';

    /**
     * @var array{
     *     summary: array{goals:int,goal_hits:int,visitors:int,avg_goal_rate:float,revenue_minor?:int,currency?:string,revenue?:array<string, int>},
     *     rows: list<array{id:string,name:string,goal_type:string,event_name:string,event_category:?string,tracked_property_name:?string,goal_hits:int,visitors:int,goal_rate:float,last_hit_at:?string,revenue_minor?:int,currency?:string,revenue?:array<string, int>}>
     * }|null
     */
    private ?array $reportCache = null;

    /** @var list<string|null> */
    private array $reportCacheParameters = [];

    public static function getNavigationSort(): int
    {
        return (int) config('filament-signals.resources.navigation_sort.goals_report', 22);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.goals_report', true)
            && static::canAccess();
    }

    /**
     * @return array{goals:int,goal_hits:int,visitors:int,avg_goal_rate:float,revenue_minor?:int,currency?:string,revenue?:array<string, int>}
     */
    public function getSummary(): array
    {
        return $this->report()['summary'];
    }

    /**
     * @return list<array{id:string,name:string,goal_type:string,event_name:string,event_category:?string,tracked_property_name:?string,goal_hits:int,visitors:int,goal_rate:float,last_hit_at:?string,revenue_minor?:int,currency?:string,revenue?:array<string, int>}>
     */
    public function getRows(): array
    {
        return $this->report()['rows'];
    }

    /**
     * @return array{
     *     summary: array{goals:int,goal_hits:int,visitors:int,avg_goal_rate:float,revenue_minor?:int,currency?:string,revenue?:array<string, int>},
     *     rows: list<array{id:string,name:string,goal_type:string,event_name:string,event_category:?string,tracked_property_name:?string,goal_hits:int,visitors:int,goal_rate:float,last_hit_at:?string,revenue_minor?:int,currency?:string,revenue?:array<string, int>}>
     * }
     */
    private function report(): array
    {
        $parameters = [
            $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
            $this->dateFrom,
            $this->dateTo,
            $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
        ];

        if ($this->reportCache === null || $this->reportCacheParameters !== $parameters) {
            $this->reportCache = app(GoalsReportService::class)->report(...$parameters);
            $this->reportCacheParameters = $parameters;
        }

        return $this->reportCache;
    }

    protected function getHeaderActions(): array
    {
        return $this->getDateRangeHeaderActions(
            app(TelemetryRead::class)->propertyOptions(),
            app(SegmentOptions::class)->getSegmentOptions(),
        );
    }
}

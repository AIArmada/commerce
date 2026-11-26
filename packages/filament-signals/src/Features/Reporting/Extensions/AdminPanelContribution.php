<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting\Extensions;

use AIArmada\FilamentSignals\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentSignals\Pages\AcquisitionReport;
use AIArmada\FilamentSignals\Pages\ContentPerformanceReport;
use AIArmada\FilamentSignals\Pages\ConversionFunnelReport;
use AIArmada\FilamentSignals\Pages\DevicesReport;
use AIArmada\FilamentSignals\Pages\GoalsReport;
use AIArmada\FilamentSignals\Pages\JourneyReport;
use AIArmada\FilamentSignals\Pages\LiveActivityReport;
use AIArmada\FilamentSignals\Pages\PageViewsReport;
use AIArmada\FilamentSignals\Pages\RetentionReport;
use AIArmada\FilamentSignals\Pages\SignalsDashboard;
use AIArmada\FilamentSignals\Resources\SavedSignalReportResource;

final class AdminPanelContribution implements ContributionContract
{
    public function pages(): array
    {
        return array_filter([
            0 => config('filament-signals.features.dashboard', true) ? SignalsDashboard::class : null,
            1 => config('filament-signals.features.page_views', true) ? PageViewsReport::class : null,
            2 => config('filament-signals.features.conversion_funnel', true) ? ConversionFunnelReport::class : null,
            3 => config('filament-signals.features.acquisition', true) ? AcquisitionReport::class : null,
            4 => config('filament-signals.features.journeys', true) ? JourneyReport::class : null,
            5 => config('filament-signals.features.retention', true) ? RetentionReport::class : null,
            6 => config('filament-signals.features.content_performance', true) ? ContentPerformanceReport::class : null,
            7 => config('filament-signals.features.live_activity', true) ? LiveActivityReport::class : null,
            8 => config('filament-signals.features.goals_report', true) ? GoalsReport::class : null,
            9 => config('filament-signals.features.devices_report', true) ? DevicesReport::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }

    public function resources(): array
    {
        return array_filter([
            3 => config('filament-signals.features.saved_reports', true) ? SavedSignalReportResource::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }
}

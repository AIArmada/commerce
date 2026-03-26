<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\FilamentSignals\FilamentSignalsPlugin;
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
use AIArmada\FilamentSignals\Resources\SignalAlertLogResource;
use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource;
use AIArmada\FilamentSignals\Resources\SignalGoalResource;
use AIArmada\FilamentSignals\Resources\SignalSegmentResource;
use AIArmada\FilamentSignals\Resources\TrackedPropertyResource;
use Filament\Panel;
use Mockery\MockInterface;

uses(FilamentSignalsTestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('registers the dashboard, report pages, and tracked property resource', function (): void {
    /** @var Panel&MockInterface $panel */
    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('pages')
        ->once()
        ->with(Mockery::on(fn (array $pages): bool => $pages === [SignalsDashboard::class, PageViewsReport::class, ConversionFunnelReport::class, AcquisitionReport::class, JourneyReport::class, RetentionReport::class, ContentPerformanceReport::class, LiveActivityReport::class, GoalsReport::class, DevicesReport::class]))
        ->andReturnSelf();
    $panel->shouldReceive('resources')
        ->once()
        ->with([TrackedPropertyResource::class, SignalGoalResource::class, SignalSegmentResource::class, SavedSignalReportResource::class, SignalAlertRuleResource::class, SignalAlertLogResource::class])
        ->andReturnSelf();

    app(FilamentSignalsPlugin::class)->register($panel);
});

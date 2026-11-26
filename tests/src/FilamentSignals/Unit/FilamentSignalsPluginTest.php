<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertRuleResource;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalGoalResource;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalInteractionRuleResource;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalSegmentResource;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\AcquisitionReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\ContentPerformanceReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\ConversionFunnelReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\DevicesReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\GoalsReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\JourneyReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\LiveActivityReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\PageViewsReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\RetentionReport;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\SignalsDashboard;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources\SavedSignalReportResource;
use AIArmada\FilamentSignals\FilamentSignalsPlugin;
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
        ->with([TrackedPropertyResource::class, SignalGoalResource::class, SignalSegmentResource::class, SavedSignalReportResource::class, SignalAlertRuleResource::class, SignalAlertLogResource::class, SignalInteractionRuleResource::class])
        ->andReturnSelf();

    app(FilamentSignalsPlugin::class)->register($panel);
});

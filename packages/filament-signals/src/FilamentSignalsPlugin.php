<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals;

use AIArmada\FilamentSignals\Pages\AcquisitionReport;
use AIArmada\FilamentSignals\Pages\ContentPerformanceReport;
use AIArmada\FilamentSignals\Pages\ConversionFunnelReport;
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
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentSignalsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-signals';
    }

    public function register(Panel $panel): void
    {
        $pages = [];
        $resources = [];

        if (config('filament-signals.features.dashboard', true)) {
            $pages[] = SignalsDashboard::class;
        }

        if (config('filament-signals.features.page_views', true)) {
            $pages[] = PageViewsReport::class;
        }

        if (config('filament-signals.features.conversion_funnel', true)) {
            $pages[] = ConversionFunnelReport::class;
        }

        if (config('filament-signals.features.acquisition', true)) {
            $pages[] = AcquisitionReport::class;
        }

        if (config('filament-signals.features.journeys', true)) {
            $pages[] = JourneyReport::class;
        }

        if (config('filament-signals.features.retention', true)) {
            $pages[] = RetentionReport::class;
        }

        if (config('filament-signals.features.content_performance', true)) {
            $pages[] = ContentPerformanceReport::class;
        }

        if (config('filament-signals.features.live_activity', true)) {
            $pages[] = LiveActivityReport::class;
        }

        if (config('filament-signals.features.goals_report', true)) {
            $pages[] = GoalsReport::class;
        }

        if (config('filament-signals.features.properties', true)) {
            $resources[] = TrackedPropertyResource::class;
        }

        if (config('filament-signals.features.goals', true)) {
            $resources[] = SignalGoalResource::class;
        }

        if (config('filament-signals.features.segments', true)) {
            $resources[] = SignalSegmentResource::class;
        }

        if (config('filament-signals.features.saved_reports', true)) {
            $resources[] = SavedSignalReportResource::class;
        }

        if (config('filament-signals.features.alert_rules', true)) {
            $resources[] = SignalAlertRuleResource::class;
        }

        if (config('filament-signals.features.alert_logs', true)) {
            $resources[] = SignalAlertLogResource::class;
        }

        $panel
            ->pages($pages)
            ->resources($resources);
    }

    public function boot(Panel $panel): void {}
}

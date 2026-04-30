<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\FilamentSignals\Resources\SavedSignalReportResource;
use AIArmada\FilamentSignals\Resources\SignalAlertLogResource;
use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource;
use AIArmada\FilamentSignals\Resources\SignalGoalResource;
use AIArmada\FilamentSignals\Resources\SignalSegmentResource;
use AIArmada\FilamentSignals\Resources\TrackedPropertyResource;

uses(FilamentSignalsTestCase::class);

it('reads resource navigation group and sort from configuration', function (): void {
    config()->set('filament-signals.navigation_group', 'Analytics Ops');
    config()->set('filament-signals.resources.navigation_sort', [
        'properties' => 101,
        'goals' => 102,
        'segments' => 103,
        'saved_reports' => 104,
        'alert_rules' => 105,
        'alert_logs' => 106,
    ]);

    expect(TrackedPropertyResource::getNavigationGroup())->toBe('Analytics Ops')
        ->and(TrackedPropertyResource::getNavigationSort())->toBe(101)
        ->and(SignalGoalResource::getNavigationGroup())->toBe('Analytics Ops')
        ->and(SignalGoalResource::getNavigationSort())->toBe(102)
        ->and(SignalSegmentResource::getNavigationGroup())->toBe('Analytics Ops')
        ->and(SignalSegmentResource::getNavigationSort())->toBe(103)
        ->and(SavedSignalReportResource::getNavigationGroup())->toBe('Analytics Ops')
        ->and(SavedSignalReportResource::getNavigationSort())->toBe(104)
        ->and(SignalAlertRuleResource::getNavigationGroup())->toBe('Analytics Ops')
        ->and(SignalAlertRuleResource::getNavigationSort())->toBe(105)
        ->and(SignalAlertLogResource::getNavigationGroup())->toBe('Analytics Ops')
        ->and(SignalAlertLogResource::getNavigationSort())->toBe(106);
});

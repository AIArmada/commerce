<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use OwenIt\Auditing\Contracts\Auditable;

it('signals control-plane models are auditable and activity loggable', function (): void {
    $models = [
        TrackedProperty::class,
        SignalAlertRule::class,
        SignalInteractionRule::class,
        SignalGoal::class,
        SignalSegment::class,
        SavedSignalReport::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(HasCommerceAudit::class)
            ->and($traits)->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeTrue();
    }
});

it('signals telemetry models intentionally remain without audit and activity traits', function (): void {
    $models = [
        SignalAlertLog::class,
        SignalDailyMetric::class,
        SignalEvent::class,
        SignalIdentity::class,
        SignalSession::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->not->toContain(HasCommerceAudit::class)
            ->and($traits)->not->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeFalse();
    }
});

<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Alerts\Actions;

use AIArmada\Signals\Features\Alerts\Api\DetachPropertyAlerts;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;

final readonly class DetachAlertsFromProperty implements DetachPropertyAlerts
{
    public function detach(string $trackedPropertyId): void
    {
        (new SignalAlertRule)->getConnection()->transaction(function () use ($trackedPropertyId): void {
            SignalAlertRule::query()->where('tracked_property_id', $trackedPropertyId)->update(['tracked_property_id' => null]);
            SignalAlertLog::query()->where('tracked_property_id', $trackedPropertyId)->update(['tracked_property_id' => null]);
        });
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Alerts\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Features\Alerts\Api\DetachPropertyAlerts;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;

final readonly class DetachAlertsFromProperty implements DetachPropertyAlerts
{
    public function detach(string $trackedPropertyId): void
    {
        (new SignalAlertRule)->getConnection()->transaction(function () use ($trackedPropertyId): void {
            $rules = SignalAlertRule::query()->where('tracked_property_id', $trackedPropertyId);
            $logs = SignalAlertLog::query()->where('tracked_property_id', $trackedPropertyId);

            if (SignalAlertRule::ownerScopingEnabled()) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Detaching alerts requires owner context.');
                $rules->forOwner($owner, includeGlobal: false);
                $logs->forOwner($owner, includeGlobal: false);
            }

            $rules->update(['tracked_property_id' => null]);
            $logs->update(['tracked_property_id' => null]);
        });
    }
}

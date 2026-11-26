<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts;

use AIArmada\FilamentSignals\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\FilamentSignals\Features\Alerts\Api\AlertDashboardWidgets;
use AIArmada\FilamentSignals\Features\Alerts\Queries\AlertDashboardWidgetList;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Policies\SignalAlertRulePolicy;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(AlertDashboardWidgets::class, AlertDashboardWidgetList::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(SignalAlertRule::class, SignalAlertRulePolicy::class);
    }
}

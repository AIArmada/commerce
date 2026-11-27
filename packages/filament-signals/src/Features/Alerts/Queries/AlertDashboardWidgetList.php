<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\Queries;

use AIArmada\FilamentSignals\Features\Alerts\Api\AlertDashboardWidgets;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Widgets\PendingSignalAlertsWidget;

final class AlertDashboardWidgetList implements AlertDashboardWidgets
{
    public function widgets(): array
    {
        return [PendingSignalAlertsWidget::class];
    }
}

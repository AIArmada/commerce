<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\Extensions;

use AIArmada\FilamentSignals\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertRuleResource;

final class AdminPanelContribution implements ContributionContract
{
    public function pages(): array
    {
        return [];
    }

    public function resources(): array
    {
        return array_filter([
            4 => config('filament-signals.features.alert_rules', true) ? SignalAlertRuleResource::class : null,
            5 => config('filament-signals.features.alert_logs', true) ? SignalAlertLogResource::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }
}

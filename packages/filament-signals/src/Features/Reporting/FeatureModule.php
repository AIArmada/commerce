<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting;

use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Policies\SavedSignalReportPolicy;
use AIArmada\FilamentSignals\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Signals\Features\Reporting\Models\SavedSignalReport;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Gate::policy(SavedSignalReport::class, SavedSignalReportPolicy::class);
    }
}

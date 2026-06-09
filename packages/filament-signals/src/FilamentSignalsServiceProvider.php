<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals;

use AIArmada\FilamentSignals\Policies\SignalAlertRulePolicy;
use AIArmada\FilamentSignals\Policies\SignalGoalPolicy;
use AIArmada\FilamentSignals\Policies\SignalInteractionRulePolicy;
use AIArmada\FilamentSignals\Policies\SignalSegmentPolicy;
use AIArmada\FilamentSignals\Policies\TrackedPropertyPolicy;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentSignalsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-signals')
            ->hasConfigFile()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentSignalsPlugin::class);
    }

    public function packageBooted(): void
    {
        Gate::policy(SignalAlertRule::class, SignalAlertRulePolicy::class);
        Gate::policy(SignalSegment::class, SignalSegmentPolicy::class);
        Gate::policy(SignalGoal::class, SignalGoalPolicy::class);
        Gate::policy(TrackedProperty::class, TrackedPropertyPolicy::class);
        Gate::policy(SignalInteractionRule::class, SignalInteractionRulePolicy::class);
    }
}

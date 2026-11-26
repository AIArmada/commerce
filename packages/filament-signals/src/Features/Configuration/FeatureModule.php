<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration;

use AIArmada\FilamentSignals\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Policies\SignalGoalPolicy;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Policies\SignalInteractionRulePolicy;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Policies\SignalSegmentPolicy;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Policies\TrackedPropertyPolicy;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Gate::policy(SignalGoal::class, SignalGoalPolicy::class);
        Gate::policy(SignalSegment::class, SignalSegmentPolicy::class);
        Gate::policy(TrackedProperty::class, TrackedPropertyPolicy::class);
        Gate::policy(SignalInteractionRule::class, SignalInteractionRulePolicy::class);
    }
}

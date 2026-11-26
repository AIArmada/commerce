<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration;

use AIArmada\FilamentSignals\Contracts\PageMarkupFetcher;
use AIArmada\FilamentSignals\Drivers\PinnedPageMarkupFetcher;
use AIArmada\FilamentSignals\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Policies\SignalGoalPolicy;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Policies\SignalInteractionRulePolicy;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Policies\SignalSegmentPolicy;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Policies\TrackedPropertyPolicy;
use AIArmada\Signals\Features\Reporting\Models\SignalGoal;
use AIArmada\Signals\Features\Ingestion\Models\SignalInteractionRule;
use AIArmada\Signals\Features\Reporting\Models\SignalSegment;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PageMarkupFetcher::class, PinnedPageMarkupFetcher::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(SignalGoal::class, SignalGoalPolicy::class);
        Gate::policy(SignalSegment::class, SignalSegmentPolicy::class);
        Gate::policy(TrackedProperty::class, TrackedPropertyPolicy::class);
        Gate::policy(SignalInteractionRule::class, SignalInteractionRulePolicy::class);
    }
}

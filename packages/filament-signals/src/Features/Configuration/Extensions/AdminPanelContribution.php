<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Configuration\Extensions;

use AIArmada\FilamentSignals\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalGoalResource;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalInteractionRuleResource;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\SignalSegmentResource;
use AIArmada\FilamentSignals\Features\Configuration\EntryPoints\Filament\Resources\TrackedPropertyResource;

final class AdminPanelContribution implements ContributionContract
{
    public function pages(): array
    {
        return [];
    }

    public function resources(): array
    {
        return array_filter([
            0 => config('filament-signals.features.properties', true) ? TrackedPropertyResource::class : null,
            1 => config('filament-signals.features.goals', true) ? SignalGoalResource::class : null,
            2 => config('filament-signals.features.segments', true) ? SignalSegmentResource::class : null,
            6 => config('filament-signals.features.interaction_rules', true) ? SignalInteractionRuleResource::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }
}

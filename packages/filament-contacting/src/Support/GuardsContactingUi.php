<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Support;

final class GuardsContactingUi
{
    public function contactMethodsReadOnly(): bool
    {
        return (bool) config('filament-contacting.resources.contact_methods.read_only', false);
    }

    public function socialProfilesReadOnly(): bool
    {
        return (bool) config('filament-contacting.resources.social_profiles.read_only', false);
    }

    public function contactSnapshotsReadOnly(): bool
    {
        return (bool) config('filament-contacting.resources.contact_snapshots.read_only', true);
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\Snapshots\Extensions;

use AIArmada\FilamentContacting\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentContacting\Features\Snapshots\EntryPoints\Filament\Resources\ContactSnapshotResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return config('filament-contacting.resources.contact_snapshots.enabled', false) ? [2 => ContactSnapshotResource::class] : [];
    }
}

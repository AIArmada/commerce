<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\ContactMethods\Extensions;

use AIArmada\FilamentContacting\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Resources\ContactMethodResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return config('filament-contacting.resources.contact_methods.enabled', false) ? [0 => ContactMethodResource::class] : [];
    }
}

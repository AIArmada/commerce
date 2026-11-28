<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Features\SocialProfiles\Extensions;

use AIArmada\FilamentContacting\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Resources\SocialProfileResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return config('filament-contacting.resources.social_profiles.enabled', false) ? [1 => SocialProfileResource::class] : [];
    }
}

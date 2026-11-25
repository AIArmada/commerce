<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Attribution\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateTouchpointResource;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 60;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            ($features['links'] ?? false) ? AffiliateLinkResource::class : null,
            ($features['attribution'] ?? false) ? AffiliateTouchpointResource::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function pages(array $features): array
    {
        return array_values(array_filter([

        ], static fn (?string $class): bool => $class !== null));
    }

    public function widgets(array $features): array
    {
        return array_values(array_filter([

        ], static fn (?string $class): bool => $class !== null));
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 40;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            ($features['programs'] ?? false) ? AffiliateProgramResource::class : null,
            ($features['creatives'] ?? false) ? AffiliateCreativeResource::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function pages(array $features): array
    {
        return [];
    }

    public function widgets(array $features): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 10;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            AffiliateResource::class,
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

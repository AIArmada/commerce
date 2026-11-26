<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Conversions\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Resources\AffiliateConversionResource;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 20;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            ($features['conversions'] ?? false) ? AffiliateConversionResource::class : null,
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
